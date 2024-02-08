<?php

declare(strict_types=1);

namespace Bouncer;

use Bouncer\Logger\Logger;
use Bouncer\Settings\Settings;
use Spatie\Emoji\Emoji;

class Target
{
    private string $id;
    private ?string $label = null;
    private array $domains;
    private string $endpointHostnameOrIp;
    private ?int $port        = null;
    private bool $letsEncrypt = false;
    private string $targetPath;
    private bool $allowNonSSL;
    private bool $useTemporaryCert      = false;
    private bool $useGlobalCert         = false;
    private ?string $customCert         = null;
    private ?string $customCertKey      = null;
    private bool $allowWebsocketSupport = true;
    private bool $allowLargePayloads    = false;
    private ?int $proxyTimeoutSeconds   = null;
    private ?string $username           = null;
    private ?string $password           = null;

    private ?string $hostOverride = null;

    public function __construct(
        private Logger $logger,
        private Settings $settings,
    ) {
        $this->allowNonSSL = $this->settings->get('ssl/allow_non_ssl', true);
    }

    public function __toArray()
    {
        if ($this->settings->has('ssl/global_cert') && $this->settings->get('ssl/global_cert') === true) {
            if ($this->getTypeCertInUse() != EnumCertType::GLOBAL_CERT) {
                $this->logger->debug('{label} has overridden cert type of {cert_type}', ['emoji' => Emoji::exclamationQuestionMark() . ' ', 'label' => $this->getLabel(), 'cert_type' => $this->getTypeCertInUse()->name]);
            }
        }

        return [
            'portHttp'  => $this->settings->get('bouncer/http_port'),
            'portHttps' => $this->settings->get('bouncer/https_port'),
        ] + [
            'id'                       => $this->getId(),
            'name'                     => $this->getName(),
            'label'                    => $this->getLabel(),
            'serverName'               => $this->getNginxServerName(),
            'certType'                 => $this->getTypeCertInUse()->name,
            'targetPath'               => $this->getTargetPath(),
            'customCertFile'           => $this->getCustomCertPath(),
            'customCertKeyFile'        => $this->getCustomCertKeyPath(),
            'useCustomCert'            => $this->isUseCustomCert(),
            'allowNonSSL'              => $this->isAllowNonSSL(),
            'allowWebsocketSupport'    => $this->isAllowWebsocketSupport(),
            'allowLargePayloads'       => $this->isAllowLargePayloads(),
            'proxyTimeoutSeconds'      => $this->getProxyTimeoutSeconds(),
            'hasAuth'                  => $this->hasAuth(),
            'authFile'                 => $this->getBasicAuthFileName(),
            'hasHostOverride'          => $this->hasHostOverride(),
            'hostOverride'             => $this->getHostOverride(),
        ];
    }

    public function getHostOverride(): ?string
    {
        return $this->hostOverride;
    }

    public function hasHostOverride(): bool
    {
        return $this->hostOverride !== null;
    }

    public function setHostOverride(string $hostOverride): self
    {
        $this->hostOverride = $hostOverride;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @param string
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getCustomCert(): ?string
    {
        return $this->customCert;
    }

    public function setCustomCert(?string $customCert): Target
    {
        $this->customCert = $customCert;

        return $this;
    }

    public function getCustomCertKey(): ?string
    {
        return $this->customCertKey;
    }

    public function setCustomCertKey(?string $customCertKey): Target
    {
        $this->customCertKey = $customCertKey;

        return $this;
    }

    public function isUseCustomCert(): bool
    {
        return $this->customCert !== null && $this->customCertKey !== null;
    }

    public function getAuth(): array
    {
        return [
            'username' => $this->getUsername(),
            'password' => $this->getPassword(),
        ];
    }

    public function getAuthHash(): string
    {
        return sha1(implode(':', $this->getAuth()));
    }

    public function setAuth(string $username, string $password): self
    {
        return $this->setUsername($username)->setPassword($password);
    }

    public function hasAuth(): bool
    {
        return $this->username != null && $this->password != null;
    }

    public function getNginxConfigFileName(): string
    {
        return "{$this->getName()}.conf";
    }

    public function getBasicAuthFileName(): string
    {
        return "{$this->getName()}.secret";
    }

    public function getBasicAuthHashFileName(): string
    {
        return "{$this->getBasicAuthFileName()}.hash";
    }

    public function getCustomCertPath(): string
    {
        return "{$this->getName()}.public.pem";
    }

    public function getCustomCertKeyPath(): string
    {
        return "{$this->getName()}.private.pem";
    }

    public function getBasicAuthFileData(): string
    {
        $output = shell_exec(sprintf('htpasswd -nibB -C10 %s %s', $this->getUsername(), $this->getPassword()));

        return trim($output) . "\n";
    }

    /**
     * Return an array of files that should exist for this target.
     */
    public function getExpectedFiles(): array
    {
        return array_filter([
            $this->getNginxConfigFileName(),
            $this->hasAuth() ? $this->getBasicAuthFileName() : null,
            $this->hasAuth() ? $this->getBasicAuthHashFileName() : null,
            $this->isUseCustomCert() ? $this->getCustomCertPath() : null,
            $this->isUseCustomCert() ? $this->getCustomCertKeyPath() : null,
        ]);
    }

    public function getProxyTimeoutSeconds(): ?int
    {
        return $this->proxyTimeoutSeconds;
    }

    public function setProxyTimeoutSeconds(?int $proxyTimeoutSeconds): self
    {
        $this->proxyTimeoutSeconds = $proxyTimeoutSeconds;

        return $this;
    }

    public function isUseTemporaryCert(): bool
    {
        return $this->useTemporaryCert;
    }

    public function setUseTemporaryCert(bool $useTemporaryCert): self
    {
        $this->useTemporaryCert = $useTemporaryCert;

        return $this;
    }

    public function isUseGlobalCert(): bool
    {
        return $this->useGlobalCert;
    }

    public function setUseGlobalCert(bool $useGlobalCert): self
    {
        // $this->logger->critical('setUseGlobalCert: {useGlobalCert}', ['useGlobalCert' => $useGlobalCert ? 'yes' : 'no']);
        $this->useGlobalCert = $useGlobalCert;

        return $this;
    }

    public function getTypeCertInUse(): EnumCertType
    {
        return match (true) {
            $this->isUseCustomCert()    => EnumCertType::CUSTOM_CERT,
            $this->isLetsEncrypt()      => EnumCertType::LETSENCRYPT_CERT,
            $this->isUseTemporaryCert() => EnumCertType::TEMPORARY_CERT,
            $this->isUseGlobalCert()    => EnumCertType::GLOBAL_CERT,
            default                     => EnumCertType::NO_CERT,
        };
    }

    public function isAllowWebsocketSupport(): bool
    {
        return $this->allowWebsocketSupport;
    }

    public function setAllowWebsocketSupport(bool $allowWebsocketSupport): self
    {
        $this->allowWebsocketSupport = $allowWebsocketSupport;

        return $this;
    }

    public function isAllowLargePayloads(): bool
    {
        return $this->allowLargePayloads;
    }

    public function setAllowLargePayloads(bool $allowLargePayloads): self
    {
        $this->allowLargePayloads = $allowLargePayloads;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getDomains(): array
    {
        return $this->domains;
    }

    public function getNginxServerNames(): array
    {
        $serverNames = [];
        foreach ($this->domains as $domain) {
            if (stripos($domain, '*') !== false) {
                $serverNames[] = sprintf('~^(.*)%s$', str_replace('*', '', $domain));
            } else {
                $serverNames[] = $domain;
            }
        }

        return $serverNames;
    }

    public function getNginxServerName(): string
    {
        return implode(' ', $this->getNginxServerNames());
    }

    /**
     * @param string[] $domains
     */
    public function setDomains(array $domains): self
    {
        $this->domains = $domains;
        $this->updateLogger();

        return $this;
    }

    public function isLetsEncrypt(): bool
    {
        return $this->letsEncrypt;
    }

    public function setLetsEncrypt(bool $letsEncrypt): self
    {
        $this->letsEncrypt = $letsEncrypt;

        return $this;
    }

    public function getTargetPath(): string
    {
        return $this->targetPath;
    }

    public function setTargetPath(string $targetPath): self
    {
        $this->targetPath = $targetPath;

        return $this;
    }

    public function getEndpointHostnameOrIp(): string
    {
        return $this->endpointHostnameOrIp;
    }

    public function setEndpointHostnameOrIp(string $endpointHostnameOrIp): self
    {
        $this->endpointHostnameOrIp = $endpointHostnameOrIp;

        return $this;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function isPortSet(): bool
    {
        return $this->port !== null;
    }

    public function setPort(int $port): self
    {
        $this->port = $port;

        return $this;
    }

    public function getName(): string
    {
        return str_replace('*.', '', reset($this->domains));
    }

    public function getLabel(): string
    {
        return $this->label ?? $this->getName();
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;
        $this->updateLogger();
        // $this->logger->debug('Target label set to {label}', ['emoji' => Emoji::label(), 'label' => $label]);

        return $this;
    }

    public function isAllowNonSSL(): bool
    {
        return $this->allowNonSSL;
    }

    public function setAllowNonSSL(bool $allowNonSSL): self
    {
        $this->allowNonSSL = $allowNonSSL;

        return $this;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function updateLogger(): self
    {
        $this->logger = $this->logger->withName($this->getLabel());

        return $this;
    }

    public function isEndpointValid(): bool
    {
        // Is it just an IP?
        if (filter_var($this->getEndpointHostnameOrIp(), FILTER_VALIDATE_IP)) {
            // $this->logger->debug(sprintf('%s isEndpointValid: %s is a normal IP', Emoji::magnifyingGlassTiltedRight(), $this->getEndpointHostnameOrIp()));

            return true;
        }

        // Is it a Hostname that resolves?
        $resolved = gethostbyname($this->getEndpointHostnameOrIp());
        if (filter_var($resolved, FILTER_VALIDATE_IP)) {
            // $this->logger->debug(sprintf('%s isEndpointValid: %s is a hostname that resolves to a normal IP %s', Emoji::magnifyingGlassTiltedRight(), $this->getEndpointHostnameOrIp(), $resolved));

            return true;
        }

        $this->logger->warning('isEndpointValid: {endpoint} is a hostname that does not resolve', ['emoji' => Emoji::magnifyingGlassTiltedRight(), 'endpoint' => $this->getEndpointHostnameOrIp()]);

        return false;
    }

    public function getPresentationDomain(): string
    {
        return sprintf(
            '%s://%s%s',
            'https',
            $this->getUsername() && $this->getPassword() ?
                sprintf('%s:%s@', $this->getUsername(), $this->getPassword()) :
                '',
            $this->getName()
        );
    }
}
