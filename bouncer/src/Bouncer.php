<?php

declare(strict_types=1);

namespace Bouncer;

use AdamBrett\ShellWrapper\Command\Builder as CommandBuilder;
use AdamBrett\ShellWrapper\Runners\Exec;
use Aws\S3\S3Client;
use Carbon\Carbon;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Monolog\Logger;
use Bouncer\Logger\Formatter;
use Spatie\Emoji\Emoji;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment as Twig;
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
    private Logger $logger;
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

        $this->logger = new \Bouncer\Logger\Logger(
            settings: $this->settings,
            processIdProcessor: new Processor\ProcessIdProcessor(),
            memoryPeakUsageProcessor: new Processor\MemoryPeakUsageProcessor(),
            psrLogMessageProcessor: new Processor\PsrLogMessageProcessor(),
            coloredLineFormatter: new Formatter\ColourLine($this->settings),
            lineFormatter: new Formatter\Line($this->settings),
        );

        if (isset($this->environment['DOCKER_HOST'])) {
            $this->logger->info('{emoji} Connecting to {docker_host}', ['emoji' => Emoji::electricPlug(), 'docker_host' => $this->environment['DOCKER_HOST']]);
            $this->docker = new Guzzle(['base_uri' => $this->environment['DOCKER_HOST']]);
        } else {
            $this->logger->info('{emoji} Connecting to {docker_host}', ['emoji' => Emoji::electricPlug(), 'docker_host' => Bouncer::DEFAULT_DOCKER_SOCKET]);
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

        // Allow defined global cert if set
        if (isset($this->environment['GLOBAL_CERT'], $this->environment['GLOBAL_CERT_KEY'])) {
            $this->setUseGlobalCert(true);
            $this->providedCertificateStore->write('global.crt', str_replace('\\n', "\n", trim($this->environment['GLOBAL_CERT'], '"')));
            $this->providedCertificateStore->write('global.key', str_replace('\\n', "\n", trim($this->environment['GLOBAL_CERT_KEY'], '"')));
            $this->logger->info("{emoji} GLOBAL_CERT was set, so we're going to use a defined certificate!", ['emoji' => Emoji::globeShowingEuropeAfrica()]);
        }

        // Determine forced update interval.
        if (isset($this->environment['BOUNCER_FORCED_UPDATE_INTERVAL_SECONDS']) && is_numeric($this->environment['BOUNCER_FORCED_UPDATE_INTERVAL_SECONDS'])) {
            $this->setForcedUpdateIntervalSeconds($this->environment['BOUNCER_FORCED_UPDATE_INTERVAL_SECONDS']);
        }
        if ($this->getForcedUpdateIntervalSeconds() > 0) {
            $this->logger->warning('{emoji}  Forced update interval is every {interval_seconds} seconds', ['emoji' => Emoji::watch(), 'interval_seconds' => $this->getForcedUpdateIntervalSeconds()]);
        } else {
            $this->logger->info('{emoji}  Forced update interval is disabled', ['emoji' => Emoji::watch()]);
        }

        // Determine maximum notices for nginx config creation.
        if (isset($this->environment['BOUNCER_MAXIMUM_NGINX_CONFIG_CREATION_NOTICES']) && is_numeric($this->environment['BOUNCER_MAXIMUM_NGINX_CONFIG_CREATION_NOTICES'])) {
            $maxConfigCreationNotices                  = intval($this->environment['BOUNCER_MAXIMUM_NGINX_CONFIG_CREATION_NOTICES']);
            $originalMaximumNginxConfigCreationNotices = $this->getMaximumNginxConfigCreationNotices();
            $this->setMaximumNginxConfigCreationNotices($maxConfigCreationNotices);
            $this->logger->warning('{emoji}  Maximum Nginx config creation notices has been over-ridden: {original} => {new}', ['emoji' => Emoji::upsideDownFace(), 'original' => $originalMaximumNginxConfigCreationNotices, 'new' => $this->getMaximumNginxConfigCreationNotices()]);
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
            $envs    = [];
            $inspect = json_decode($this->docker->request('GET', "containers/{$container['Id']}/json")->getBody()->getContents(), true);
            if (isset($inspect['Config']['Env'])) {
                foreach ($inspect['Config']['Env'] as $environmentItem) {
                    if (stripos($environmentItem, '=') !== false) {
                        [$envKey, $envVal] = explode('=', $environmentItem, 2);
                        $envs[$envKey]     = $envVal;
                    } else {
                        $envs[$environmentItem] = true;
                    }
                }
            }
            if (isset($envs['BOUNCER_DOMAIN'])) {
                $bouncerTarget = (new Target($this->logger))
                    ->setId($inspect['Id'])
                ;
                $bouncerTarget = $this->parseContainerEnvironmentVariables($envs, $bouncerTarget);

                if (isset($inspect['NetworkSettings']['IPAddress']) && !empty($inspect['NetworkSettings']['IPAddress'])) {
                    // As per docker service
                    $bouncerTarget->setEndpointHostnameOrIp($inspect['NetworkSettings']['IPAddress']);
                } else {
                    // As per docker compose
                    $networks = array_values($inspect['NetworkSettings']['Networks']);
                    $bouncerTarget->setEndpointHostnameOrIp($networks[0]['IPAddress']);
                }

                $bouncerTarget->setTargetPath(sprintf('http://%s:%d/', $bouncerTarget->getEndpointHostnameOrIp(), $bouncerTarget->getPort() >= 0 ? $bouncerTarget->getPort() : 80));

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
            $this->logger->debug('{emoji} Something happened while interrogating services.. This node is not a swarm node, cannot have services: {message}', ['emoji' => Emoji::warning(), 'message' => $services['message']]);
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
                foreach ($service['Spec']['TaskTemplate']['ContainerSpec']['Env'] as $env) {
                    [$eKey, $eVal] = explode('=', $env, 2);
                    $envs[$eKey]   = $eVal;
                }
                if (isset($envs['BOUNCER_DOMAIN'])) {
                    $bouncerTarget = (new Target($this->logger))
                        ->setId($service['ID'])
                    ;
                    $bouncerTarget = $this->parseContainerEnvironmentVariables($envs, $bouncerTarget);

                    if ($bouncerTarget->isPortSet()) {
                        $bouncerTarget->setEndpointHostnameOrIp($service['Spec']['Name']);
                        // $this->logger->info('{emoji} Ports for {target_name} has been explicitly set to {host}:{port}.', ['emoji' => Emoji::warning(), 'target_name' => $bouncerTarget->getName(), 'host' => $bouncerTarget->getEndpointHostnameOrIp(), 'port' => $bouncerTarget->getPort()]);
                    } elseif (isset($service['Endpoint']['Ports'])) {
                        $bouncerTarget->setEndpointHostnameOrIp('172.17.0.1');
                        $bouncerTarget->setPort(intval($service['Endpoint']['Ports'][0]['PublishedPort']));
                    } else {
                        $this->logger->warning('{emoji} Ports block missing for {target_name}.', ['emoji' => Emoji::warning(), 'target_name' => $bouncerTarget->getName()]);

                        continue;
                    }
                    $bouncerTarget->setTargetPath(sprintf('http://%s:%d/', $bouncerTarget->getEndpointHostnameOrIp(), $bouncerTarget->getPort()));

                    $bouncerTarget->setUseGlobalCert($this->isUseGlobalCert());

                    if ($bouncerTarget->isEndpointValid()) {
                        $bouncerTargets[] = $bouncerTarget;
                    } else {
                        $this->logger->debug(
                            '{emoji} Decided that {target_name} has the endpoint {endpoint} and it is not valid.',
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
        $gitHash    = substr($this->environment['GIT_SHA'], 0, 7);
        $buildDate  = Carbon::parse($this->environment['BUILD_DATE']);
        $gitMessage = trim($this->environment['GIT_COMMIT_MESSAGE']);
        $this->logger->info('{emoji}  Starting Bouncer. Built on {build_date}, {build_ago}', ['emoji' => Emoji::redHeart(), 'build_date' => $buildDate->toDateTimeString(), 'build_ago' => $buildDate->ago()]);
        $this->logger->info('{emoji} Build #{git_sha}: "{git_message}"', ['emoji' => Emoji::memo(), 'git_sha' => $gitHash, 'git_message' => $gitMessage]);

        try {
            $this->stateHasChanged();
        } catch (ConnectException $connectException) {
            $this->logger->critical('{emoji} Could not connect to docker socket! Did you map it?', ['emoji' => Emoji::cryingCat()]);

            exit;
        }
        while (true) {
            $this->runLoop();
        }
    }

    public function parseContainerEnvironmentVariables(array $envs, Target $bouncerTarget): Target
    {
        foreach ($envs as $eKey => $eVal) {
            switch ($eKey) {
                case 'BOUNCER_LABEL':
                    $bouncerTarget->setLabel($eVal);

                    break;

                case 'BOUNCER_DOMAIN':
                    $domains = explode(',', $eVal);
                    array_walk($domains, function (&$domain, $key): void {
                        $domain = trim($domain);
                    });
                    $bouncerTarget->setDomains($domains);

                    break;

                case 'BOUNCER_AUTH':
                    [$username, $password] = explode(':', $eVal);
                    $bouncerTarget->setAuth($username, $password);

                    break;

                case 'BOUNCER_HOST_OVERRIDE':
                    $bouncerTarget->setHostOverride($eVal);

                    break;

                case 'BOUNCER_LETSENCRYPT':
                    $bouncerTarget->setLetsEncrypt(in_array(strtolower($eVal), ['yes', 'true'], true));

                    break;

                case 'BOUNCER_TARGET_PORT':
                    $bouncerTarget->setPort(intval($eVal));

                    break;

                case 'BOUNCER_ALLOW_NON_SSL':
                    $bouncerTarget->setAllowNonSSL(in_array(strtolower($eVal), ['yes', 'true'], true));

                    break;

                case 'BOUNCER_ALLOW_WEBSOCKETS':
                    $bouncerTarget->setAllowWebsocketSupport(in_array(strtolower($eVal), ['yes', 'true'], true));

                    break;

                case 'BOUNCER_ALLOW_LARGE_PAYLOADS':
                    $bouncerTarget->setAllowLargePayloads(in_array(strtolower($eVal), ['yes', 'true'], true));

                    break;

                case 'BOUNCER_PROXY_TIMEOUT_SECONDS':
                    $bouncerTarget->setProxyTimeoutSeconds(is_numeric($eVal) ? intval($eVal) : null);

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

    private function dockerEnvHas(string $key, ?array $envs): bool
    {
        if ($envs === null) {
            return false;
        }

        foreach ($envs as $env) {
            if (stripos($env, '=') !== false) {
                [$envKey, $envVal] = explode('=', $env, 2);
                if ($envKey === $key) {
                    return true;
                }
            }
        }

        return false;
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
            $this->logger->warning('{emoji}  Forced update interval of {interval_seconds} seconds has been reached, forcing update.', ['emoji' => Emoji::watch(), 'interval_seconds' => $this->forcedUpdateIntervalSeconds]);
            $isTainted = true;
        } elseif ($this->previousContainerState === []) {
            $this->logger->warning('{emoji}  Initial state has not been set, forcing update.', ['emoji' => Emoji::watch()]);
            $isTainted = true;
        } elseif ($this->previousSwarmState === []) {
            $this->logger->warning('{emoji}  Initial swarm state has not been set, forcing update.', ['emoji' => Emoji::watch()]);
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
            $this->logger->warning('{emoji}  Container state has changed', ['emoji' => Emoji::warning()]);
            echo $containerStateDiff;
            $isTainted = true;
        }
        $this->previousContainerState = $newContainerState;

        // Swarm Services
        $newSwarmState = [];
        if ($this->isSwarmMode()) {
            $services = json_decode($this->docker->request('GET', 'services')->getBody()->getContents(), true);
            if (isset($services['message'])) {
                $this->logger->warning('{emoji} Something happened while interrogating services.. This node is not a swarm node, cannot have services: {message}', ['emoji' => Emoji::warning(), 'message' => $services['message']]);
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
            $this->logger->warning('{emoji}  Swarm state has changed', ['emoji' => Emoji::warning()]);
            echo $swarmStateDiff;
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
            $this->logger->critical('{emoji} Unable to connect to docker socket!', ['emoji' => Emoji::warning()]);
            $this->logger->critical($exception->getMessage());

            exit(1);
        }

        $this->logger->info('{emoji} Swarm mode is {enabled}.', ['emoji' => Emoji::honeybee(), 'enabled' => $this->isSwarmMode() ? 'enabled' : 'disabled']);

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

        // Wipe configs and rebuild
        $this->wipeNginxConfig();

        $this->logger->info('{emoji} Found {num_services} services with BOUNCER_DOMAIN set', ['emoji' => Emoji::magnifyingGlassTiltedLeft(), 'num_services' => count($targets)]);
        $this->generateNginxConfigs($targets);
        $this->generateLetsEncryptCerts($targets);
        if ($this->s3Enabled()) {
            $this->writeCertificatesToS3();
        }
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
        $this->logger->info('{emoji}  Uploading Certificates to S3', ['emoji' => Emoji::CHARACTER_UP_ARROW]);
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
                $changedTargets[] = $target;
            }
        }
        if (count($changedTargets) <= $this->getMaximumNginxConfigCreationNotices()) {
            foreach ($changedTargets as $target) {
                $this->logger->info('{emoji}  Created {label}', ['emoji' => Emoji::pencil(), 'label' => $target->getLabel()]);
                $this->logger->debug('{emoji}       -> {certs_dir}/{file}', ['emoji' => Emoji::pencil(), 'certs_dir' => Bouncer::FILESYSTEM_CONFIG_DIR, 'file' => $target->getFileName()]);
                $this->logger->debug('{emoji}       -> {domain}', ['emoji' => Emoji::pencil(), 'domain' => $target->getPresentationDomain()]);
            }
        } else {
            $this->logger->info('{emoji}  More than {num_max} Nginx configs generated.. Too many to show them all!', ['emoji' => Emoji::pencil(), 'num_max' => $this->getMaximumNginxConfigCreationNotices()]);
        }
        $this->logger->info('{emoji}  Updated {num_created} Nginx configs, {num_changed} changed..', ['emoji' => Emoji::pencil(), 'num_created' => count($targets), 'num_changed' => count($changedTargets)]);
    }

    private function generateNginxConfig(Target $target): bool
    {
        $configData     = $this->twig->render('NginxTemplate.twig', $target->__toArray());
        $changed        = false;
        $configFileHash = $this->configFilesystem->fileExists($target->getFileName()) ? sha1($this->configFilesystem->read($target->getFileName())) : null;

        if (sha1($configData) != $configFileHash) {
            $this->configFilesystem->write($target->getFileName(), $configData);
            $changed = true;
        }

        if ($target->hasAuth()) {
            $authFileHash   = $this->configFilesystem->fileExists($target->getAuthFileName()) ? sha1($this->configFilesystem->read($target->getAuthFileName())) : null;
            if (sha1($target->getAuthFileData()) != $authFileHash) {
                $this->configFilesystem->write($target->getAuthFileName(), $target->getAuthFileData());
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
                        '{emoji} Skipping {target_name}, certificate is {validity} for {duration_days} days',
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
            $this->logger->debug('{emoji} Moving nginx tweak file out of the way..', ['emoji' => Emoji::rightArrow()]);
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
            $this->logger->info('{emoji} Generating letsencrypt for {target_name} - {command}', ['emoji' => Emoji::pencil(), 'target_name' => $target->getName(), 'command' => $command->__toString()]);
            $shell->run($command);

            if ($shell->getReturnValue() == 0) {
                $this->logger->info('{emoji} Generating successful', ['emoji' => Emoji::partyPopper()]);
            } else {
                $this->logger->critical('{emoji} Generating failed!', ['emoji' => Emoji::warning()]);
            }

            // Re-enable nginx tweaks
            $this->logger->debug('{emoji} Moving nginx tweak file back in place..', ['emoji' => Emoji::leftArrow()]);
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
        $this->logger->info('{emoji}  Restarting nginx', ['emoji' => Emoji::timerClock()]);
        $shell->run($command);
    }

    private function wipeNginxConfig(): void
    {
        $this->logger->debug('{emoji} Purging existing config files ...', ['emoji' => Emoji::bomb()]);
        foreach ($this->configFilesystem->listContents('') as $file) {
            /** @var FileAttributes $file */
            if ($file->isFile() && $file->path() != 'default.conf' && $file->path() != 'default-ssl.conf') {
                $this->configFilesystem->delete($file->path());
            }
        }
    }
}
