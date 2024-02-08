<?php

declare(strict_types=1);

namespace Bouncer\Settings;

use Carbon\Carbon;
use Monolog\Level;

class Settings implements SettingsInterface
{
    private array $settings;

    public static function getEnvironment(mixed $key, mixed $default = null): mixed
    {
        if (is_array($key)) {
            foreach ($key as $k) {
                $value = Settings::getEnvironment($k);
                if ($value !== null) {
                    return $value;
                }
            }

            return $default;
        }
        $environment = array_merge($_ENV, $_SERVER);

        return $environment[$key] ?? $default;
    }

    public static function isEnabled(string $key, bool $default = false): bool
    {
        $environment      = array_merge($_ENV, $_SERVER);
        $expressionsOfYes = ['true', 'enable', 'enabled', 'yes', 'on'];

        if (isset($environment[$key])) {
            return in_array(strtolower($environment[$key]), $expressionsOfYes);
        }

        return $default;
    }

    public function __construct()
    {
        $this->settings = [
            'build' => [
                'sha'       => Settings::getEnvironment('GIT_SHA', 'unknown'),
                'sha_short' => substr(Settings::getEnvironment('GIT_SHA', 'unknown'), 0, 7),
                'id'        => Settings::getEnvironment('GIT_BUILD_ID', 'unknown'),
                'date'      => Carbon::parse(Settings::getEnvironment('BUILD_DATE')),
                'message'   => trim(Settings::getEnvironment('GIT_COMMIT_MESSAGE', '')),
            ],
            'logger'                  => [
                'name'                  => Settings::getEnvironment('LOG_NAME', 'bouncer'),
                'path'                  => Settings::getEnvironment('LOG_FILE', '/var/log/bouncer/bouncer.log'),
                'level'                 => Level::fromName(Settings::getEnvironment('LOG_LEVEL', 'DEBUG')),
                'line_format'           => Settings::getEnvironment('LOG_LINE_FORMAT', '[%datetime%] %context.emoji% %level_name%: %channel%: %message%') . "\n",
                'max_level_name_length' => Settings::getEnvironment('LOG_LEVEL_NAME_LENGTH', 4),
                'coloured_output'       => Settings::isEnabled('LOG_COLOUR', true),
                'show_state_deltas'     => Settings::isEnabled('LOG_SHOW_STATE_DELTAS'),
            ],
            'ssl' => [
                'allow_non_ssl'   => Settings::isEnabled('BOUNCER_ALLOW_NON_SSL', true),
                'global_cert'     => Settings::getEnvironment('GLOBAL_CERT'),
                'global_cert_key' => Settings::getEnvironment('GLOBAL_CERT_KEY'),
            ],
            'bouncer' => [
                'http_port'                         => intval(Settings::getEnvironment('BOUNCER_HTTP_PORT', 80)),
                'https_port'                        => intval(Settings::getEnvironment('BOUNCER_HTTPS_PORT', 443)),
                'forced_update_interval_seconds'    => intval(Settings::getEnvironment('BOUNCER_FORCED_UPDATE_INTERVAL_SECONDS', 0)),
                'max_nginx_config_creation_notices' => intval(Settings::getEnvironment('BOUNCER_MAXIMUM_NGINX_CONFIG_CREATION_NOTICES', 15)),
            ],
        ];
    }

    public function get(string $key = '', mixed $default = null): mixed
    {
        if (stripos($key, '/') !== false) {
            $s     = $this->settings;
            $steps = explode('/', $key);
            while (count($steps) > 0) {
                $b = array_shift($steps);
                if (isset($s[$b])) {
                    $s = $s[$b];
                } else {
                    return $default;
                }
            }

            return $s;
        }

        return (empty($key)) ? $this->settings : $this->settings[$key];
    }

    public function has(string $key = ''): bool
    {
        return $this->get($key) !== null;
    }

    public function if(string $key): bool
    {
        return $this->has($key) && $this->get($key) == true;
    }

    public function set(string $key, mixed $value): self
    {
        $keys         = explode('/', $key);
        $arrayPointer = &$this->settings;

        // extract the last key
        $last_key = array_pop($keys);

        // walk/build the array to the specified key
        while ($arrayKey = array_shift($keys)) {
            if (!array_key_exists($arrayKey, $arrayPointer)) {
                $arrayPointer[$arrayKey] = [];
            }
            $arrayPointer = &$arrayPointer[$arrayKey];
        }

        // set the final key
        $arrayPointer[$last_key] = $value;

        return $this;
    }
}
