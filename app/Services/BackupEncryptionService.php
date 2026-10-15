<?php

namespace App\Services;

use RuntimeException;

class BackupEncryptionService
{
    private const MAGIC = "AIRMIUS-BACKUP-V1\n";

    private const CHUNK_SIZE = 1024 * 1024;

    public function encrypt(string $source, string $target): array
    {
        $key = $this->key();
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        $input = fopen($source, 'rb');
        $output = fopen($target, 'wb');

        if ($input === false || $output === false) {
            throw new RuntimeException('Backup encryption streams could not be opened.');
        }

        try {
            fwrite($output, self::MAGIC.$header);
            $chunk = fread($input, self::CHUNK_SIZE);
            while ($chunk !== false && $chunk !== '') {
                $next = fread($input, self::CHUNK_SIZE);
                if ($next === false) {
                    throw new RuntimeException('Backup source could not be read during encryption.');
                }
                $tag = $next === '' && feof($input)
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                $ciphertext = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);
                fwrite($output, pack('N', strlen($ciphertext)).$ciphertext);
                $chunk = $next;
            }
            if ($chunk === false) {
                throw new RuntimeException('Backup source could not be read during encryption.');
            }
        } finally {
            fclose($input);
            fclose($output);
            sodium_memzero($key);
        }

        return ['algorithm' => 'sodium-secretstream-xchacha20poly1305', 'format' => 1];
    }

    public function decrypt(string $source, string $target): void
    {
        $key = $this->key();
        $input = fopen($source, 'rb');
        $output = fopen($target, 'wb');

        if ($input === false || $output === false) {
            throw new RuntimeException('Backup decryption streams could not be opened.');
        }

        try {
            $prefix = fread($input, strlen(self::MAGIC));
            $header = fread($input, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if ($prefix !== self::MAGIC || strlen($header) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
                throw new RuntimeException('Encrypted backup header is invalid.');
            }
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
            $finalSeen = false;
            while (! feof($input)) {
                $lengthBytes = fread($input, 4);
                if ($lengthBytes === '') {
                    break;
                }
                if (strlen($lengthBytes) !== 4) {
                    throw new RuntimeException('Encrypted backup frame is truncated.');
                }
                $length = unpack('Nlength', $lengthBytes)['length'];
                $ciphertext = '';
                while (strlen($ciphertext) < $length && ! feof($input)) {
                    $part = fread($input, $length - strlen($ciphertext));
                    if ($part === false) {
                        throw new RuntimeException('Encrypted backup frame could not be read.');
                    }
                    $ciphertext .= $part;
                }
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $ciphertext);
                if ($result === false) {
                    throw new RuntimeException('Backup authentication failed.');
                }
                [$plaintext, $tag] = $result;
                fwrite($output, $plaintext);
                $finalSeen = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
                if ($finalSeen) {
                    break;
                }
            }
            if (! $finalSeen) {
                throw new RuntimeException('Encrypted backup is incomplete.');
            }
        } finally {
            fclose($input);
            fclose($output);
            sodium_memzero($key);
        }
    }

    private function key(): string
    {
        $encoded = (string) config('airmius_backup.encryption_key');
        $key = base64_decode($encoded, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY must be a base64-encoded 32-byte key.');
        }

        return $key;
    }
}
