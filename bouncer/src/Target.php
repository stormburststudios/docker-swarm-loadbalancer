<?php

declare(strict_types=1);

namespace Bouncer;

use Monolog\Logger;
use Spatie\Emoji\Emoji;

class Target
{
    private string $id;
    private array $domains;
    private string $endpointHostnameOrIp;
    private ?int $port        = null;
    private bool $letsEncrypt = false;
    private string $targetPath;
    private bool $allowNonSSL           = true;
    private bool $useTemporaryCert      = true;
    private bool $useGlobalCert         = false;
    private bool $allowWebsocketSupport = true;
    private bool $allowLargePayloads    = false;
    private ?int $proxyTimeoutSeconds   = null;
    private ?string $username           = null;
    private ?string $password           = null;

    private ?string $hostOverride = null;

    public function __construct(
        private Logger $logger
    ) {
    }

    public function __toArray()
    {
        return [
            'id'                    => $this->getId(),
            'name'                  => $this->getName(),
            'domains'               => $this->getDomains(),
            'letsEncrypt'           => $this->isLetsEncrypt(),
            'targetPath'            => $this->getTargetPath(),
            'useTemporaryCert'      => $this->isUseTemporaryCert(),
            'useGlobalCert'         => $this->isUseGlobalCert(),
            'allowNonSSL'           => $this->isAllowNonSSL(),
            'allowWebsocketSupport' => $this->isAllowWebsocketSupport(),
            'allowLargePayloads'    => $this->isAllowLargePayloads(),
            'proxyTimeoutSeconds'   => $this->getProxyTimeoutSeconds(),
            'hasAuth'               => $this->hasAuth(),
            'authFile'              => $this->getAuthFileName(),
            'hasHostOverride'       => $this->hasHostOverride(),
            'hostOverride'          => $this->getHostOverride(),
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

    public function setAuth(string $username, string $password): self
    {
        return $this->setUsername($username)->setPassword($password);
    }

    public function hasAuth(): bool
    {
        return $this->username != null && $this->password != null;
    }

    public function getFileName(): string
    {
        return "{$this->getName()}.conf";
    }

    public function getAuthFileName(): string
    {
        return "{$this->getName()}.secret";
    }

    public function getAuthFileData(): string
    {
        $output = shell_exec(sprintf('htpasswd -nibB -C10 %s %s', $this->getUsername(), $this->getPassword()));

        return trim($output) . "\n";
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
        $this->useGlobalCert = $useGlobalCert;

        // Global cert overrides temporary certs.
        if ($useGlobalCert) {
            $this->setUseTemporaryCert(false);
        }

        return $this;
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

    /**
     * @param string[] $domains
     */
    public function setDomains(array $domains): self
    {
        $this->domains = $domains;

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

    public function getName()
    {
        return reset($this->domains);
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

        $this->logger->warning('{emoji} isEndpointValid: {endpoint} is a hostname that does not resolve', ['emoji' => Emoji::magnifyingGlassTiltedRight(), 'endpoint' => $this->getEndpointHostnameOrIp()]);

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
