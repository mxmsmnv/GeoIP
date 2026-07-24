<?php

final class GeoIPStore
{
    public function __construct(
        private readonly object $database,
        private readonly object $session,
        private readonly object $sanitizer,
        private readonly object $log
    ) {
    }

    public function getCorrection(string $ip): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM `' . GeoIP::TABLE_CORRECTIONS . '` WHERE ip = :ip ORDER BY created DESC LIMIT 1'
        );
        $statement->execute([':ip' => $ip]);
        return $statement->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function saveCorrection(string $ip, array $data): bool
    {
        $statement = $this->database->prepare(
            'INSERT INTO `' . GeoIP::TABLE_CORRECTIONS . '`
                (ip, country, country_code, region, region_code, city, created)
             VALUES
                (:ip, :country, :cc, :region, :rc, :city, NOW())
             ON DUPLICATE KEY UPDATE
                country=VALUES(country), country_code=VALUES(country_code),
                region=VALUES(region), region_code=VALUES(region_code),
                city=VALUES(city), created=NOW()'
        );

        return $statement->execute([
            ':ip' => $ip,
            ':country' => $this->sanitizer->text($data['country'] ?? ''),
            ':cc' => strtoupper($this->sanitizer->text($data['country_code'] ?? '')),
            ':region' => $this->sanitizer->text($data['region'] ?? ''),
            ':rc' => strtoupper($this->sanitizer->text($data['region_code'] ?? '')),
            ':city' => $this->sanitizer->text($data['city'] ?? ''),
        ]);
    }

    public function logLookup(array $data): void
    {
        $loggedIPs = $this->session->get('geoip_logged') ?? [];
        if (in_array($data['ip'], $loggedIPs, true)) {
            return;
        }

        try {
            $statement = $this->database->prepare(
                'INSERT INTO `' . GeoIP::TABLE_LOG . '`
                    (ip, country_code, region_code, city, status, created)
                 VALUES (:ip, :cc, :rc, :city, :status, NOW())'
            );
            $statement->execute([
                ':ip' => $data['ip'],
                ':cc' => $data['countryCode'] ?? '',
                ':rc' => $data['regionCode'] ?? '',
                ':city' => $data['city'] ?? '',
                ':status' => $data['status'] ?? '',
            ]);
            $loggedIPs[] = $data['ip'];
            $this->session->set('geoip_logged', $loggedIPs);
        } catch (\Throwable $exception) {
            $this->log->save('geoip', 'logLookup error: ' . $exception->getMessage());
        }
    }

    public function createTables(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS `' . GeoIP::TABLE_LOG . '` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ip` VARCHAR(45) NOT NULL DEFAULT \'\',
                `country_code` VARCHAR(2) NOT NULL DEFAULT \'\',
                `region_code` VARCHAR(10) NOT NULL DEFAULT \'\',
                `city` VARCHAR(100) NOT NULL DEFAULT \'\',
                `status` VARCHAR(20) NOT NULL DEFAULT \'\',
                `created` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                INDEX `idx_ip` (`ip`),
                INDEX `idx_created` (`created`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS `' . GeoIP::TABLE_CORRECTIONS . '` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ip` VARCHAR(45) NOT NULL DEFAULT \'\',
                `country` VARCHAR(100) NOT NULL DEFAULT \'\',
                `country_code` VARCHAR(2) NOT NULL DEFAULT \'\',
                `region` VARCHAR(100) NOT NULL DEFAULT \'\',
                `region_code` VARCHAR(10) NOT NULL DEFAULT \'\',
                `city` VARCHAR(100) NOT NULL DEFAULT \'\',
                `created` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_ip` (`ip`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
}
