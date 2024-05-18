<?php

declare(strict_types=1);

namespace Bouncer;

use AdamBrett\ShellWrapper\Command\Builder as CommandBuilder;
use AdamBrett\ShellWrapper\Runners\Exec;
use Aws\S3\S3Client;
use Bouncer\Logger\AbstractLogger;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Bouncer\Logger\Logger;
use Bouncer\Logger\Formatter;
use Spatie\Emoji\Emoji;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment as Twig;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader as TwigLoader;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Processor;
use Bouncer\Settings\Settings;

class Bouncer
{
    private array $environment;
    private Guzzle $docker;
    private TwigLoader $loader;
    private Twig $twig;
    private Filesystem $configFilesystem;
    private Filesystem $certificateStoreLocal;
    private ?Filesystem $certificateStoreRemote = null;
    private Filesystem $providedCertificateStore;
    private AbstractLogger $logger;
    private array $previousContainerState = [];
    private array $previousSwarmState     = [];
    private array $fileHashes;
    private bool $swarmMode                        = false;
    private bool $useGlobalCert                    = false;
    private int $forcedUpdateIntervalSeconds       = 0;
    private ?int $lastUpdateEpoch                  = null;
    private int $maximumNginxConfigCreationNotices = 15;
    private Settings $settings;

    private const DEFAULT_DOCKER_SOCKET          = '/var/run/docker.sock';
    private const FILESYSTEM_CONFIG_DIR          = '/etc/nginx/sites-enabled';
    private const FILESYSTEM_CERTS_DIR           = '/etc/nginx/certs';
    private const FILESYSTEM_CERTS_PROVIDED_DIR  = '/certs';

    public function __construct()
    {
        $this->environment = array_merge($_ENV, $_SERVER);
        ksort($this->environment);

        $this->settings = new Settings();

        $this->logger = new Logger(
            settings: $this->settings,
            processIdProcessor: new Processor\ProcessIdProcessor(),
            memoryPeakUsageProcessor: new Processor\MemoryPeakUsageProcessor(),
            psrLogMessageProcessor: new Processor\PsrLogMessageProcessor(),
            coloredLineFormatter: new Formatter\ColourLine($this->settings),
            lineFormatter: new Formatter\Line($this->settings),
        );

        if (isset($this->environment['DOCKER_HOST'])) {
            $this->logger->info('Connecting to {docker_host}', ['emoji' => Emoji::electricPlug(), 'docker_host' => $this->environment['DOCKER_HOST']]);
            $this->docker = new Guzzle(['base_uri' => $this->environment['DOCKER_HOST']]);
        } else {
            $this->logger->info('Connecting to {docker_host}', ['emoji' => Emoji::electricPlug(), 'docker_host' => Bouncer::DEFAULT_DOCKER_SOCKET]);
            $this->docker = new Guzzle(['base_uri' => 'http://localhost', 'curl' => [CURLOPT_UNIX_SOCKET_PATH => Bouncer::DEFAULT_DOCKER_SOCKET]]);
        }

        $this->loader = new TwigLoader([__DIR__ . '/../templates']);
        $this->twig   = new Twig($this->loader);

        // Set up Filesystem for sites-enabled path
        $this->configFilesystem = new Filesystem(new LocalFilesystemAdapter(Bouncer::FILESYSTEM_CONFIG_DIR));

        // Set up Local certificate store
        $this->certificateStoreLocal = new Filesystem(new LocalFilesystemAdapter(Bouncer::FILESYSTEM_CERTS_DIR));

        // Set up Local certificate store for certificates provided to us
        $this->providedCertificateStore = new Filesystem(new LocalFilesystemAdapter(Bouncer::FILESYSTEM_CERTS_PROVIDED_DIR));

        // Set up Remote certificate store, if configured
        if (isset($this->environment['BOUNCER_S3_BUCKET'])) {
            $this->certificateStoreRemote = new Filesystem(
                new AwsS3V3Adapter(
                    new S3Client([
                        'endpoint'                => $this->environment['BOUNCER_S3_ENDPOINT'],
                        'use_path_style_endpoint' => isset($this->environment['BOUNCER_S3_USE_PATH_STYLE_ENDPOINT']),
                        'credentials'             => [
                            'key'    => $this->environment['BOUNCER_S3_KEY_ID'],
                            'secret' => $this->environment['BOUNCER_S3_KEY_SECRET'],
                        ],
                        'region'  => $this->environment['BOUNCER_S3_REGION'] ?? 'us-east',
                        'version' => 'latest',
                    ]),
                    $this->environment['BOUNCER_S3_BUCKET'],
                    $this->environment['BOUNCER_S3_PREFIX'] ?? ''
                )
            );
        }
    }

    public function getMaximumNginxConfigCreationNotices(): int
    {
        return $this->maximumNginxConfigCreationNotices;
    }

    public function setMaximumNginxConfigCreationNotices(int $maximumNginxConfigCreationNotices): Bouncer
    {
        $this->maximumNginxConfigCreationNotices = $maximumNginxConfigCreationNotices;

        return $this;
    }

    public function isSwarmMode(): bool
    {
        return $this->swarmMode;
    }

    public function setSwarmMode(bool $swarmMode): Bouncer
    {
        $this->swarmMode = $swarmMode;

        return $this;
    }

    public function isUseGlobalCert(): bool
    {
        return $this->useGlobalCert;
    }

    public function setUseGlobalCert(bool $useGlobalCert): Bouncer
    {
        $this->useGlobalCert = $useGlobalCert;

        return $this;
    }

    public function getForcedUpdateIntervalSeconds(): int
    {
        return $this->forcedUpdateIntervalSeconds;
    }

    public function setForcedUpdateIntervalSeconds(int $forcedUpdateIntervalSeconds): Bouncer
    {
        $this->forcedUpdateIntervalSeconds = $forcedUpdateIntervalSeconds;

        return $this;
    }

    /**
     * @return Target[]
     *
     * @throws GuzzleException
     */
    public function findContainersContainerMode(): array
    {
        $bouncerTargets = [];

        $containers = json_decode($this->docker->request('GET', 'containers/json')->getBody()->getContents(), true);
        foreach ($containers as $container) {
            $envs      = [];
            $container = json_decode($this->docker->request('GET', "containers/{$container['Id']}/json")->getBody()->getContents(), true);
            if (
                !isset($container['Config']['Env'])
            ) {
                continue;
            }
            // Parse all the environment variables and store them in an array.
            foreach ($container['Config']['Env'] as $env) {
                [$envKey, $envVal] = explode('=', $env, 2);
                if (str_starts_with($envKey, 'BOUNCER_')) {
                    $envs[$envKey] = $envVal;
                }
            }
            ksort($envs);
            // If there are no BOUNCER_* environment variables, skip this service.
            if (count($envs) == 0) {
                continue;
            }
            // If BOUNCER_IGNORE is set, skip this service.
            if (isset($envs['BOUNCER_IGNORE'])) {
                continue;
            }

            if (isset($envs['BOUNCER_DOMAIN'])) {
                $bouncerTarget = (new Target(
                    logger: $this->logger,
                    settings: $this->settings,
                ))
                    ->setId($container['Id'])
                ;
                $bouncerTarget = $this->parseContainerEnvironmentVariables($envs, $bouncerTarget);

                if (!empty($container['NetworkSettings']['IPAddress'])) {
                    // As per docker service
                    $bouncerTarget->setEndpointHostnameOrIp($container['NetworkSettings']['IPAddress']);
                } else {
                    // As per docker compose
                    $networks = array_values($container['NetworkSettings']['Networks']);
                    $bouncerTarget->setEndpointHostnameOrIp($networks[0]['IPAddress']);
                }

                $bouncerTarget->setTargetPath(sprintf('http://%s:%d', $bouncerTarget->getEndpointHostnameOrIp(), $bouncerTarget->getPort() >= 0 ? $bouncerTarget->getPort() : 80));

                $bouncerTarget->setUseGlobalCert($this->isUseGlobalCert());

                $valid = $bouncerTarget->isEndpointValid();
                // $this->logger->debug(sprintf(
                //    '%s Decided that %s has the endpoint %s and it %s.',
                //    Emoji::magnifyingGlassTiltedLeft(),
                //    $bouncerTarget->getName(),
                //    $bouncerTarget->getEndpointHostnameOrIp(),
                //    $valid ? 'is valid' : 'is not valid'
                // ));
                if ($valid) {
                    $bouncerTargets[] = $bouncerTarget;
                }
            }
        }

        return $bouncerTargets;
    }

    public function findContainersSwarmMode(): array
    {
        $bouncerTargets = [];
        $services       = json_decode($this->docker->request('GET', 'services')->getBody()->getContents(), true);

        if (isset($services['message'])) {
            $this->logger->debug('Something happened while interrogating services.. This node is not a swarm node, cannot have services: {message}', ['emoji' => Emoji::warning() . ' Bouncer.php', 'message' => $services['message']]);
        } else {
            foreach ($services as $service) {
                $envs = [];
                if (
                    !isset($service['Spec'])
                    || !isset($service['Spec']['TaskTemplate'])
                    || !isset($service['Spec']['TaskTemplate']['ContainerSpec'])
                    || !isset($service['Spec']['TaskTemplate']['ContainerSpec']['Env'])
                ) {
                    continue;
                }
                // Parse all the environment variables and store them in an array.
                foreach ($service['Spec']['TaskTemplate']['ContainerSpec']['Env'] as $env) {
                    [$envKey, $envVal] = explode('=', $env, 2);
                    if (str_starts_with($envKey, 'BOUNCER_')) {
                        $envs[$envKey] = $envVal;
                    }
                }
                ksort($envs);
                // If there are no BOUNCER_* environment variables, skip this service.
                if (count($envs) == 0) {
                    continue;
                }
                // if BOUNCER_IGNORE is set, skip this service.
                if (isset($envs['BOUNCER_IGNORE'])) {
                    continue;
                }

                $bouncerTarget = (new Target(
                    logger: $this->logger,
                    settings: $this->settings,
                ));
                if (isset($envs['BOUNCER_LABEL'])) {
                    $bouncerTarget->setLabel($envs['BOUNCER_LABEL']);
                }
                if (isset($envs['BOUNCER_DOMAIN'])) {
                    $bouncerTarget->setId($service['ID']);
                    $bouncerTarget->setLabel($service['Spec']['Name']);
                    $bouncerTarget = $this->parseContainerEnvironmentVariables($envs, $bouncerTarget);

                    if ($bouncerTarget->hasCustomNginxConfig()) {
                        $this->logger->info('Custom nginx config for {label} is provided.', ['emoji' => Emoji::artistPalette(), 'label' => $bouncerTarget->getLabel()]);
                        $bouncerTargets[] = $bouncerTarget;

                        continue;
                    }
                    if ($bouncerTarget->isPortSet()) {
                        $bouncerTarget->setEndpointHostnameOrIp($service['Spec']['Name']);
                        // $this->logger->info('{label}: Ports for {target_name} has been explicitly set to {host}:{port}.', ['emoji' => Emoji::warning().' ', 'target_name' => $bouncerTarget->getName(), 'host' => $bouncerTarget->getEndpointHostnameOrIp(), 'port' => $bouncerTarget->getPort()]);
                    } elseif (isset($service['Endpoint']['Ports'])) {
                        $bouncerTarget->setEndpointHostnameOrIp('172.17.0.1');
                        $bouncerTarget->setPort(intval($service['Endpoint']['Ports'][0]['PublishedPort']));
                    } else {
                        $this->logger->warning('{label}: ports block missing for {target_name}. Try setting BOUNCER_TARGET_PORT.', ['emoji' => Emoji::warning() . ' Bouncer.php', 'label' => $bouncerTarget->getLabel(), 'target_name' => $bouncerTarget->getName()]);
                        \Kint::dump(
                            $bouncerTarget->getId(),
                            $bouncerTarget->getLabel(),
                            $envs
                        );

                        continue;
                    }
                    $bouncerTarget->setTargetPath(sprintf('http://%s:%d', $bouncerTarget->getEndpointHostnameOrIp(), $bouncerTarget->getPort()));

                    $bouncerTarget->setUseGlobalCert($this->isUseGlobalCert());

                    // @phpstan-ignore-next-line MB: I'm not sure you're right about ->hasCustomNginxConfig only returning false, Stan..
                    if ($bouncerTarget->isEndpointValid() || $bouncerTarget->hasCustomNginxConfig()) {
                        $bouncerTargets[] = $bouncerTarget;
                    } else {
                        $this->logger->debug(
                            'Decided that {target_name} has the endpoint {endpoint} and it is not valid.',
                            [
                                'emoji'       => Emoji::magnifyingGlassTiltedLeft(),
                                'target_name' => $bouncerTarget->getName(),
                                'endpoint'    => $bouncerTarget->getEndpointHostnameOrIp(),
                            ]
                        );
                    }
                }
            }
        }

        return $bouncerTargets;
    }

    public function run(): void
    {
        $this->logger->info('Starting Bouncer. Built {build_id} on {build_date}, {build_ago}', ['emoji' => Emoji::redHeart() . ' Bouncer.php', 'build_id' => $this->settings->get('build/id'), 'build_date' => $this->settings->get('build/date')->toDateTimeString(), 'build_ago' => $this->settings->get('build/date')->ago()]);
        $this->logger->info('Build #{git_sha}: "{build_message}"', ['emoji' => Emoji::memo(), 'git_sha' => $this->settings->get('build/sha_short'), 'build_message' => $this->settings->get('build/message')]);
        $this->logger->debug(' > HTTPS Listener is on {https_port}', ['emoji' => Emoji::ship(), 'https_port' => $this->settings->get('bouncer/https_port')]);
        $this->logger->debug(' > HTTP Listener is on {http_port}', ['emoji' => Emoji::ship(), 'http_port' => $this->settings->get('bouncer/http_port')]);

        // Allow defined global cert if set
        if ($this->settings->has('ssl/global_cert') && $this->settings->has('ssl/global_cert_key')) {
            $this->setUseGlobalCert(true);
            $this->providedCertificateStore->write('global.crt', str_replace('\\n', "\n", trim($this->settings->get('ssl/global_cert'), '"')));
            $this->providedCertificateStore->write('global.key', str_replace('\\n', "\n", trim($this->settings->get('ssl/global_cert_key'), '"')));
        }
        $this->logger->debug(' > Global Cert is {enabled}', ['emoji' => Emoji::globeShowingEuropeAfrica(), 'enabled' => $this->isUseGlobalCert() ? 'enabled' : 'disabled']);

        // Determine forced update interval.
        if ($this->settings->has('bouncer/forced_update_interval_seconds')) {
            $this->setForcedUpdateIntervalSeconds($this->settings->get('bouncer/forced_update_interval_seconds'));
        }
        $this->logger->debug(' > Forced Update Interval is {state}', ['emoji' => Emoji::watch(), 'state' => $this->getForcedUpdateIntervalSeconds() > 0 ? $this->getForcedUpdateIntervalSeconds() : 'disabled']);

        // Determine maximum notices for nginx config creation.
        if ($this->settings->has('bouncer/max_nginx_config_creation_notices')) {
            $maxConfigCreationNotices                  = $this->settings->get('bouncer/max_nginx_config_creation_notices');
            $originalMaximumNginxConfigCreationNotices = $this->getMaximumNginxConfigCreationNotices();
            $this->setMaximumNginxConfigCreationNotices($maxConfigCreationNotices);
            $this->logger->debug(' > Maximum Nginx config creation notices has been over-ridden: {original} => {new}', ['emoji' => Emoji::hikingBoot(), 'original' => $originalMaximumNginxConfigCreationNotices, 'new' => $this->getMaximumNginxConfigCreationNotices()]);
        }

        // State if non-SSL is allowed. This is processed in the Target class.
        $this->logger->debug(' > Allow non-SSL is {enabled}', ['emoji' => Emoji::ship(), 'enabled' => $this->settings->get('ssl/allow_non_ssl') ? 'enabled' : 'disabled']);

        try {
            $this->stateHasChanged();
        } catch (ConnectException $connectException) {
            $this->logger->critical('Could not connect to docker socket! Did you forget to map it?', ['emoji' => Emoji::cryingCat()]);

            exit(1);
        }
        // @phpstan-ignore-next-line Yes, I know this is a loop, that is desired.
        while (true) {
            $this->runLoop();
        }
    }

    public function parseContainerEnvironmentVariables(array $envs, Target $bouncerTarget): Target
    {
        // Process label and name specifically before all else.
        foreach (array_filter($envs) as $envKey => $envVal) {
            switch ($envKey) {
                case 'BOUNCER_LABEL':
                    $bouncerTarget->setLabel($envVal);

                    break;

                case 'BOUNCER_DOMAIN':
                    $domains = explode(',', $envVal);
                    array_walk($domains, function (&$domain, $key): void {
                        $domain = trim($domain);
                    });
                    $bouncerTarget->setDomains($domains);

                    break;
            }
        }
        foreach (array_filter($envs) as $envKey => $envVal) {
            switch ($envKey) {
                case 'BOUNCER_AUTH':
                    [$username, $password] = explode(':', $envVal);
                    $bouncerTarget->setAuth($username, $password);
                    // $this->logger->info('{label}: Basic Auth has been enabled.', ['emoji' => Emoji::key(), 'label' => $bouncerTarget->getLabel(),]);

                    break;

                case 'BOUNCER_HOST_OVERRIDE':
                    $bouncerTarget->setHostOverride($envVal);
                    $this->logger->warning('{label}: Host reported to container overridden and set to {host_override}.', ['emoji' => Emoji::hikingBoot(), 'label' => $bouncerTarget->getLabel(), 'host_override' => $bouncerTarget->getHostOverride()]);

                    break;

                case 'BOUNCER_LETSENCRYPT':
                    $bouncerTarget->setLetsEncrypt(in_array(strtolower($envVal), ['yes', 'true'], true));

                    break;

                case 'BOUNCER_CERT':
                    $bouncerTarget->setCustomCert($envVal);
                    $this->logger->info('{label}: Custom cert specified', ['emoji' => Emoji::locked(), 'label' => $bouncerTarget->getLabel()]);

                    break;

                case 'BOUNCER_CERT_KEY':
                    $bouncerTarget->setCustomCertKey($envVal);

                    break;

                case 'BOUNCER_TARGET_PORT':
                    $bouncerTarget->setPort(intval($envVal));
                    // $this->logger->info('{label}: Target port set to {port}.', ['emoji' => Emoji::ship(), 'label' => $bouncerTarget->getLabel(), 'port' => $bouncerTarget->getPort(),]);

                    break;

                case 'BOUNCER_ALLOW_NON_SSL':
                    $bouncerTarget->setAllowNonSSL(in_array(strtolower($envVal), ['yes', 'true'], true));

                    break;

                case 'BOUNCER_ALLOW_WEBSOCKETS':
                    $bouncerTarget->setAllowWebsocketSupport(in_array(strtolower($envVal), ['yes', 'true'], true));

                    break;

                case 'BOUNCER_ALLOW_LARGE_PAYLOADS':
                    $bouncerTarget->setAllowLargePayloads(in_array(strtolower($envVal), ['yes', 'true'], true));

                    break;

                case 'BOUNCER_PROXY_TIMEOUT_SECONDS':
                    $bouncerTarget->setProxyTimeoutSeconds(is_numeric($envVal) ? intval($envVal) : null);

                    break;

                case 'BOUNCER_CUSTOM_NGINX_CONFIG':
                    // If envval is base64 encoded, decode it first
                    if (preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $envVal)) {
                        $envVal = base64_decode($envVal);
                    }
                    $this->logger->info('Custom nginx config for {label} is provided.', ['emoji' => Emoji::artistPalette(), 'label' => $bouncerTarget->getLabel()]);
                    $bouncerTarget->setCustomNginxConfig($envVal);

                    break;
            }
        }

        return $bouncerTarget;
    }

    private function dockerGetContainers(): array
    {
        return json_decode($this->docker->request('GET', 'containers/json')->getBody()->getContents(), true);
    }

    private function dockerGetContainer(string $id): array
    {
        return json_decode($this->docker->request('GET', "containers/{$id}/json")->getBody()->getContents(), true);
    }

    private function dockerEnvFilter(?array $envs): array
    {
        if ($envs === null) {
            return [];
        }

        $envs = array_filter(array_map(function ($env) {
            if (stripos($env, '=') !== false) {
                [$envKey, $envVal] = explode('=', $env, 2);

                if (strlen($envVal) > 65) {
                    return sprintf('%s=CRC32(%s)', $envKey, crc32($envVal));
                }

                return sprintf('%s=%s', $envKey, $envVal);
            }

            return $env;
        }, $envs));

        sort($envs);

        return $envs;
    }

    /**
     * Returns true when something has changed.
     *
     * @throws GuzzleException
     */
    private function stateHasChanged(): bool
    {
        $isTainted = false;
        if ($this->lastUpdateEpoch === null) {
            $isTainted = true;
        } elseif ($this->forcedUpdateIntervalSeconds > 0 && $this->lastUpdateEpoch <= time() - $this->forcedUpdateIntervalSeconds) {
            $this->logger->warning('Forced update interval of {interval_seconds} seconds has been reached, forcing update.', ['emoji' => Emoji::watch(), 'interval_seconds' => $this->forcedUpdateIntervalSeconds]);
            $isTainted = true;
        } elseif ($this->previousContainerState === []) {
            $this->logger->warning('Initial state has not been set, forcing update.', ['emoji' => Emoji::watch()]);
            $isTainted = true;
        } elseif ($this->previousSwarmState === []) {
            $this->logger->warning('Initial swarm state has not been set, forcing update.', ['emoji' => Emoji::watch()]);
            $isTainted = true;
        }

        // Standard Containers
        $newContainerState = [];
        $containers        = $this->dockerGetContainers();
        foreach ($containers as $container) {
            $inspect                  = $this->dockerGetContainer($container['Id']);
            $name                     = ltrim($inspect['Name'], '/');
            $env                      = $inspect['Config']['Env'] ?? [];
            // if (!$this->dockerEnvHas('BOUNCER_DOMAIN', $env)) {
            //    continue;
            // }

            $newContainerState[$name] = [
                'name'    => $name,
                'created' => $inspect['Created'],
                'image'   => $inspect['Image'],
                'status'  => $inspect['State']['Status'],
                'env'     => $this->dockerEnvFilter($env),
            ];
            if (is_array($newContainerState[$name]['env'])) {
                sort($newContainerState[$name]['env']);
            }
        }
        ksort($newContainerState);

        // Calculate Container State Hash
        $containerStateDiff = $this->diff($this->previousContainerState, $newContainerState);
        if (!$isTainted && !empty($containerStateDiff)) {
            if ($this->settings->if('logger/show_state_deltas')) {
                $this->logger->warning('Container state has changed', ['emoji' => Emoji::warning() . ' Bouncer.php']);
                echo $containerStateDiff;
            }
            $isTainted = true;
        }
        $this->previousContainerState = $newContainerState;

        // Swarm Services
        $newSwarmState = [];
        if ($this->isSwarmMode()) {
            $services = json_decode($this->docker->request('GET', 'services')->getBody()->getContents(), true);
            if (isset($services['message'])) {
                $this->logger->warning('Something happened while interrogating services.. This node is not a swarm node, cannot have services: {message}', ['emoji' => Emoji::warning() . ' Bouncer.php', 'message' => $services['message']]);
            } else {
                foreach ($services as $service) {
                    $name                 = $service['Spec']['Name'];
                    $env                  = $service['Spec']['TaskTemplate']['ContainerSpec']['Env'] ?? [];
                    // if (!$this->dockerEnvHas('BOUNCER_DOMAIN', $env)) {
                    //    continue;
                    // }
                    $newSwarmState[$name] = [
                        'id'           => $service['ID'],
                        'mode'         => isset($service['Spec']['Mode']['Replicated']) ?
                            sprintf('replicated:%d', $service['Spec']['Mode']['Replicated']['Replicas']) :
                            (isset($service['Spec']['Mode']['Global']) ? 'global' : 'none'),
                        'created'      => $service['CreatedAt'],
                        'image'        => $service['Spec']['TaskTemplate']['ContainerSpec']['Image'],
                        'versionIndex' => $service['Version']['Index'],
                        'updateStatus' => $service['UpdateStatus']['State'] ?? 'unknown',
                        'env'          => $this->dockerEnvFilter($env),
                    ];
                }
            }
        }
        ksort($newSwarmState);

        // Calculate Swarm State Hash, if applicable
        $swarmStateDiff = $this->diff($this->previousSwarmState, $newSwarmState);
        if ($this->isSwarmMode() && !$isTainted && !empty($swarmStateDiff)) {
            if ($this->settings->if('logger/show_state_deltas')) {
                $this->logger->warning('Swarm state has changed', ['emoji' => Emoji::warning() . ' Bouncer.php']);
                echo $swarmStateDiff;
            }
            $isTainted = true;
        }
        $this->previousSwarmState = $newSwarmState;

        return $isTainted;
    }

    private function diff($a, $b)
    {
        return (new \Diff(
            explode(
                "\n",
                Yaml::dump(input: $a, inline: 5, indent: 2)
            ),
            explode(
                "\n",
                Yaml::dump(input: $b, inline: 5, indent: 2)
            )
        ))->render(new \Diff_Renderer_Text_Unified());
    }

    private function runLoop(): void
    {
        if ($this->s3Enabled()) {
            $this->getCertificatesFromS3();
        }

        try {
            $determineSwarmMode = json_decode($this->docker->request('GET', 'swarm')->getBody()->getContents(), true);
            $this->setSwarmMode(!isset($determineSwarmMode['message']));
        } catch (ServerException $exception) {
            $this->setSwarmMode(false);
        } catch (ConnectException $exception) {
            $this->logger->critical('Unable to connect to docker socket!', ['emoji' => Emoji::warning() . ' Bouncer.php']);
            $this->logger->critical($exception->getMessage());

            exit(1);
        }

        $this->logger->debug(' > Swarm mode is {enabled}.', ['emoji' => Emoji::honeybee(), 'enabled' => $this->isSwarmMode() ? 'enabled' : 'disabled']);

        $targets = array_values(
            array_merge(
                $this->findContainersContainerMode(),
                $this->isSwarmMode() ? $this->findContainersSwarmMode() : []
            )
        );

        // Use some bs to sort the targets by domain from right to left.
        $sortedTargets = [];
        foreach ($targets as $target) {
            $sortedTargets[strrev($target->getName())] = $target;
        }
        ksort($sortedTargets);
        $targets = array_values($sortedTargets);

        // Re-generate nginx configs
        $this->logger->info('Found {num_services} services with BOUNCER_DOMAIN set', ['emoji' => Emoji::magnifyingGlassTiltedLeft(), 'num_services' => count($targets)]);
        $this->generateNginxConfigs($targets);
        $this->generateLetsEncryptCerts($targets);
        if ($this->s3Enabled()) {
            $this->writeCertificatesToS3();
        }

        // if any of the targets has requiresForcedScanning set to true, we need to force an update
        if (array_reduce($targets, fn ($carry, $target) => $carry || $target->requiresForcedScanning(), false)) {
            $this->logger->warning('Forcing an update in 5 seconds because one or more targets require it.', ['emoji' => Emoji::warning()]);
            sleep(5);

            return;
        }

        // Wait for next change
        $this->waitUntilContainerChange();
    }

    private function waitUntilContainerChange(): void
    {
        while ($this->stateHasChanged() === false) {
            sleep(5);
        }
        $this->lastUpdateEpoch = time();
    }

    private function s3Enabled(): bool
    {
        return $this->certificateStoreRemote instanceof Filesystem;
    }

    private function getCertificatesFromS3(): void
    {
        $this->logger->info(sprintf('%s Downloading Certificates from S3', Emoji::CHARACTER_DOWN_ARROW));
        foreach ($this->certificateStoreRemote->listContents('/', true) as $file) {
            /** @var FileAttributes $file */
            if ($file->isFile()) {
                $localPath = "archive/{$file->path()}";
                if ($file->fileSize() == 0) {
                    $this->logger->warning(sprintf(' > Downloading %s to %s was skipped, because it was empty', $file->path(), $localPath));

                    continue;
                }
                $this->logger->debug(sprintf(' > Downloading %s to %s (%d bytes)', $file->path(), $localPath, $file->fileSize()));
                $this->certificateStoreLocal->writeStream($localPath, $this->certificateStoreRemote->readStream($file->path()));
                if ($this->certificateStoreLocal->fileSize($localPath) == $this->certificateStoreRemote->fileSize($file->path())) {
                    $this->logger->debug(sprintf('   > Filesize for %s matches %s on remote (%d bytes)', $localPath, $file->path(), $this->certificateStoreLocal->fileSize($localPath)));
                } else {
                    $this->logger->critical(sprintf('   > Filesize for %s DOES NOT MATCH %s on remote (%d != %d bytes)', $localPath, $file->path(), $this->certificateStoreLocal->fileSize($localPath), $this->certificateStoreRemote->fileSize($file->path())));
                }
                $this->fileHashes[$localPath] = sha1($this->certificateStoreLocal->read($localPath));
            }
        }

        // Copy certs into /live because certbot is a pain.
        foreach ($this->certificateStoreLocal->listContents('/archive', true) as $newLocalCert) {
            /** @var FileAttributes $newLocalCert */
            if ($newLocalCert->isFile() && pathinfo($newLocalCert->path(), PATHINFO_EXTENSION) == 'pem') {
                $livePath = str_replace('archive/', 'live/', $newLocalCert->path());
                // Stupid dirty hack bullshit reee
                for ($i = 1; $i <= 9; ++$i) {
                    $livePath = str_replace("{$i}.pem", '.pem', $livePath);
                }
                $this->logger->debug(sprintf(' > Mirroring %s to %s (%d bytes)', $newLocalCert->path(), $livePath, $newLocalCert->fileSize()));
                $this->certificateStoreLocal->writeStream($livePath, $this->certificateStoreLocal->readStream($newLocalCert->path()));
            }
        }
    }

    private function fileChanged(string $localPath)
    {
        if (!isset($this->fileHashes[$localPath])) {
            return true;
        }
        if (sha1($this->certificateStoreLocal->read($localPath)) != $this->fileHashes[$localPath]) {
            return true;
        }

        return false;
    }

    private function writeCertificatesToS3(): void
    {
        $this->logger->info('Uploading Certificates to S3', ['emoji' => Emoji::CHARACTER_UP_ARROW]);
        foreach ($this->certificateStoreLocal->listContents('/archive', true) as $file) {
            /** @var FileAttributes $file */
            if ($file->isFile()) {
                $remotePath = str_replace('archive/', '', $file->path());
                if ($file->fileSize() == 0) {
                    $this->logger->warning(' > Skipping uploading {file}, file is garbage (empty).', ['file' => $file->path()]);
                } elseif (!$this->certificateStoreRemote->fileExists($remotePath) || $this->fileChanged($file->path())) {
                    $this->logger->debug(' > Uploading {file} ({bytes} bytes)', ['file' => $file->path(), 'bytes' => $file->fileSize()]);
                    $this->certificateStoreRemote->write($remotePath, $this->certificateStoreLocal->read($file->path()));
                } else {
                    $this->logger->debug(' > Skipping uploading {file}, file not changed.', ['file' => $file->path()]);
                }
            }
        }
    }

    /**
     * @param $targets Target[]
     */
    private function generateNginxConfigs(array $targets): void
    {
        $changedTargets = [];
        foreach ($targets as $target) {
            if ($this->generateNginxConfig($target)) {
                $changedTargets[strrev($target->getName())] = $target;
            }
        }
        // @var Target[] $changedTargets
        ksort($changedTargets);
        $changedTargets = array_values($changedTargets);

        if (count($changedTargets) <= $this->getMaximumNginxConfigCreationNotices()) {
            /** @var Target $target */
            foreach ($changedTargets as $target) {
                $context = [
                    'label'      => $target->getLabel(),
                    'domain'     => $target->getPresentationdomain(),
                    'file'       => $target->getNginxConfigFileName(),
                    'config_dir' => Bouncer::FILESYSTEM_CONFIG_DIR,
                ];
                $this->logger->info('Created {label}', $context + ['emoji' => Emoji::pencil() . ' Bouncer.php']);
                $this->logger->debug('  -> {config_dir}/{file}', $context + ['emoji' => Emoji::pencil() . ' Bouncer.php']);
                $this->logger->debug('  -> {domain}', $context + ['emoji' => Emoji::pencil() . ' Bouncer.php']);
                $this->logger->critical('{label} cert type is {cert_type}', $context + ['emoji' => Emoji::catFace(), 'cert_type' => $target->getTypeCertInUse()->name]);
            }
        } else {
            $this->logger->info('More than {num_max} Nginx configs generated.. Too many to show them all!', ['emoji' => Emoji::pencil() . ' Bouncer.php', 'num_max' => $this->getMaximumNginxConfigCreationNotices()]);
        }
        $this->logger->info('Updated {num_created} Nginx configs, {num_changed} changed..', ['emoji' => Emoji::pencil() . ' Bouncer.php', 'num_created' => count($targets), 'num_changed' => count($changedTargets)]);

        $this->pruneNonExistentConfigs($targets);
    }

    /**
     * @param $targets Target[]
     *
     * @throws FilesystemException
     */
    protected function pruneNonExistentConfigs(array $targets): void
    {
        $expectedFiles = [
            'default.conf',
        ];
        foreach ($targets as $target) {
            $expectedFiles = array_merge($expectedFiles, $target->getExpectedFiles());
        }
        foreach ($this->configFilesystem->listContents('/') as $file) {
            if (!in_array($file['path'], $expectedFiles)) {
                $this->logger->info('Removing {file}', ['emoji' => Emoji::wastebasket(), 'file' => $file['path']]);
                $this->configFilesystem->delete($file['path']);
            }
        }
    }

    /**
     * @throws FilesystemException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function generateNginxConfig(Target $target): bool
    {
        $configData     = $target->hasCustomNginxConfig() ? $target->getCustomNginxConfig() : $this->twig->render('NginxTemplate.twig', $target->__toArray());
        $changed        = false;
        $configFileHash = $this->configFilesystem->fileExists($target->getNginxConfigFileName()) ? sha1($this->configFilesystem->read($target->getNginxConfigFileName())) : null;

        if (sha1($configData) != $configFileHash) {
            $this->configFilesystem->write($target->getNginxConfigFileName(), $configData);
            $changed = true;
        }

        if ($target->isUseCustomCert()) {
            $this->configFilesystem->write($target->getCustomCertPath(), $target->getCustomCert());
            $this->configFilesystem->write($target->getCustomCertKeyPath(), $target->getCustomCertKey());
        }

        if ($target->hasAuth()) {
            $authFileHash   = $this->configFilesystem->fileExists($target->getBasicAuthFileName()) ? $this->configFilesystem->read($target->getBasicAuthHashFileName()) : null;
            if ($target->getAuthHash() != $authFileHash) {
                $this->configFilesystem->write($target->getBasicAuthHashFileName(), $target->getAuthHash());
                $this->configFilesystem->write($target->getBasicAuthFileName(), $target->getBasicAuthFileData());
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * @param Target[] $targets
     *
     * @throws FilesystemException
     */
    private function generateLetsEncryptCerts(array $targets): void
    {
        foreach ($targets as $target) {
            if (!$target->isLetsEncrypt()) {
                continue;
            }

            $testAgeFile = "/archive/{$target->getName()}/fullchain1.pem";
            if ($this->certificateStoreLocal->fileExists($testAgeFile)) {
                $dubious = false;
                if ($this->certificateStoreLocal->fileSize($testAgeFile) == 0) {
                    // File is empty, check its age instead.
                    $timeRemainingSeconds = $this->certificateStoreLocal->lastModified($testAgeFile) - time();
                    $dubious              = true;
                } else {
                    $ssl                  = openssl_x509_parse($this->certificateStoreLocal->read($testAgeFile));
                    $timeRemainingSeconds = $ssl['validTo_time_t'] - time();
                }
                if ($timeRemainingSeconds > 2592000) {
                    $this->logger->info(
                        'Skipping {target_name}, certificate is {validity} for {duration_days} days',
                        [
                            'emoji'         => Emoji::CHARACTER_PARTYING_FACE,
                            'target_name'   => $target->getName(),
                            'validity'      => $dubious ? 'dubiously good' : 'still good',
                            'duration_days' => round($timeRemainingSeconds / 86400),
                        ]
                    );

                    $target->setUseTemporaryCert(false);
                    $this->generateNginxConfig($target);

                    continue;
                }
            }

            // Start running shell commands...
            $shell = new Exec();

            // Disable nginx tweaks
            $this->logger->debug('Moving nginx tweak file out of the way..', ['emoji' => Emoji::rightArrow()]);
            $disableNginxTweaksCommand = (new CommandBuilder('mv'))
                ->addSubCommand('/etc/nginx/conf.d/tweak.conf')
                ->addSubCommand('/etc/nginx/conf.d/tweak.disabled')
            ;
            $shell->run($disableNginxTweaksCommand);

            // Generate letsencrypt cert
            $command = new CommandBuilder('/usr/bin/certbot');
            $command->addSubCommand('certonly');
            $command->addArgument('nginx');
            if ($this->environment['BOUNCER_LETSENCRYPT_MODE'] != 'production') {
                $command->addArgument('test-cert');
            }
            $command->addFlag('d', implode(',', $target->getDomains()));
            $command->addFlag('n');
            $command->addFlag('m', $this->environment['BOUNCER_LETSENCRYPT_EMAIL']);
            $command->addArgument('agree-tos');
            $this->logger->info('Generating letsencrypt for {target_name} - {command}', ['emoji' => Emoji::pencil() . ' Bouncer.php', 'target_name' => $target->getName(), 'command' => $command->__toString()]);
            $shell->run($command);

            if ($shell->getReturnValue() == 0) {
                $this->logger->info('Generating successful', ['emoji' => Emoji::partyPopper()]);
            } else {
                $this->logger->critical('Generating failed!', ['emoji' => Emoji::warning() . ' Bouncer.php']);
            }

            // Re-enable nginx tweaks
            $this->logger->debug('Moving nginx tweak file back in place..', ['emoji' => Emoji::leftArrow()]);
            $disableNginxTweaksCommand = (new CommandBuilder('mv'))
                ->addSubCommand('/etc/nginx/conf.d/tweak.disabled')
                ->addSubCommand('/etc/nginx/conf.d/tweak.conf')
            ;
            $shell->run($disableNginxTweaksCommand);

            $target->setUseTemporaryCert(false);
            $this->generateNginxConfig($target);
        }

        $this->restartNginx();
    }

    private function restartNginx(): void
    {
        $shell   = new Exec();
        $command = new CommandBuilder('/usr/sbin/nginx');
        $command->addFlag('s', 'reload');
        $this->logger->info('Restarting nginx', ['emoji' => Emoji::timerClock() . ' Bouncer.php']);
        $nginxRestartOutput = $shell->run($command);
        $this->logger->debug('Nginx restarted {restart_output}', ['restart_output' => $nginxRestartOutput, 'emoji' => Emoji::partyPopper()]);
    }
}
