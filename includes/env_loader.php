<?php

function gggReadEnvFile(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('No se pudo leer el archivo .env.');
    }
    if (substr($contents, 0, 3) === "\xEF\xBB\xBF") {
        $contents = substr($contents, 3);
    }

    $values = [];
    foreach (preg_split('/\r\n|\n|\r/', $contents) as $index => $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (preg_match('/^export[ \t]+/', $line, $exportMatch)) {
            $line = substr($line, strlen($exportMatch[0]));
        }
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)[ \t]*=[ \t]*(.*)$/', $line, $matches)) {
            throw new RuntimeException('Formato no válido en .env, línea ' . ($index + 1) . '.');
        }

        $key = $matches[1];
        if (array_key_exists($key, $values)) {
            throw new RuntimeException('La variable ' . $key . ' está duplicada en .env.');
        }

        $rawValue = trim($matches[2]);
        if ($rawValue !== '' && ($rawValue[0] === '"' || $rawValue[0] === "'")) {
            $quote = $rawValue[0];
            $value = '';
            $closed = false;
            $length = strlen($rawValue);
            for ($i = 1; $i < $length; $i++) {
                $character = $rawValue[$i];
                if ($character === $quote) {
                    $suffix = trim(substr($rawValue, $i + 1));
                    if ($suffix !== '' && $suffix[0] !== '#') {
                        throw new RuntimeException('Formato no válido en .env, línea ' . ($index + 1) . '.');
                    }
                    $closed = true;
                    break;
                }
                if ($quote === '"' && $character === '\\' && $i + 1 < $length) {
                    $next = $rawValue[$i + 1];
                    if ($next === '\\' || $next === '"' || $next === '$') {
                        $value .= $next;
                        $i++;
                        continue;
                    }
                }
                $value .= $character;
            }
            if (!$closed) {
                throw new RuntimeException('Comillas sin cerrar en .env, línea ' . ($index + 1) . '.');
            }
        } else {
            $value = preg_replace('/[ \t]+#.*$/', '', $rawValue);
            $value = trim($value);
        }

        $values[$key] = $value;
    }

    return $values;
}

function gggLoadEnvFile(string $path): void
{
    foreach (gggReadEnvFile($path) as $key => $value) {
        if (getenv($key) !== false || isset($_ENV[$key]) || isset($_SERVER[$key])) {
            continue;
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}
