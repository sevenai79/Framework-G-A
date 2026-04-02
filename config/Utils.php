<?php

namespace Config;

class Utils
{
    //I parametri della class sono al momento inutilizzati, ma potrebbero essere utili in futuro per funzioni che necessitano di accesso al database o alla sessione
    private $db;
    const URL = "https://santihubslr.it/";
    const BACKEND_URL = "https://santihubslr.it/backend/";
    public function __construct($db)
    {
        $this->db = $db;
    }

    public function sanitizeMixedArray($data, $allowHtml = false)
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $cleanKey = is_string($key)
                ? $this->sanitizeString($key, false)
                : $key;

            $sanitized[$cleanKey] = $this->sanitizeValue($value, $allowHtml);
        }

        return $sanitized;
    }

    private function sanitizeValue($value, $allowHtml)
    {
        if (is_array($value)) {
            return $this->sanitizeMixedArray($value, $allowHtml);
        }

        if (is_string($value)) {
            return $this->sanitizeString($value, $allowHtml);
        }

        return $value;
    }

    private function sanitizeString($value, $allowHtml)
    {
        $value = trim($value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';

        if (!$allowHtml) {
            $value = strip_tags($value);
        }

        return $value;
    }

    public function getUrl()
    {
        return self::URL;
    }

    public function getBackendUrl()
    {
        return self::BACKEND_URL;
    }
}

