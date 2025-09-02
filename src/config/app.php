<?php
class AppConfig {
    
    const APP_NAME = 'EDI Processing Module';
    const APP_VERSION = '1.0.0';
    const COMPANY_NAME = 'Greenfield Precision Plastics LLC';
    
    const TIMEZONE = 'America/New_York';
    const DATE_FORMAT = 'Y-m-d';
    const DATETIME_FORMAT = 'Y-m-d H:i:s';
    
    const EDI_VERSION = '004';
    const EDI_RELEASE = '001';
    const EDI_SEGMENT_TERMINATOR = '~';
    const EDI_ELEMENT_SEPARATOR = '*';
    const EDI_SUBELEMENT_SEPARATOR = ':';
    
    const DEFAULT_UOM = 'EACH';
    const DEFAULT_ORGANIZATION = 'NIFCO';
    const DEFAULT_SUPPLIER = 'GREENFIELD PRECISION PLASTICS LLC';
    
    public static function getDataPath($subdir = '') {
        $base = dirname(dirname(__DIR__)) . '/data';
        return $subdir ? $base . '/' . $subdir : $base;
    }
    
    public static function getInboxPath() {
        return self::getDataPath('inbox');
    }
    
    public static function getOutboxPath() {
        return self::getDataPath('outbox');
    }
    
    public static function getArchivePath() {
        return self::getDataPath('archive');
    }
    
    public static function getTempPath() {
        $temp = sys_get_temp_dir() . '/edi_processing';
        if (!is_dir($temp)) {
            mkdir($temp, 0755, true);
        }
        return $temp;
    }
    
    public static function getLogPath() {
        $log = dirname(dirname(__DIR__)) . '/logs';
        if (!is_dir($log)) {
            mkdir($log, 0755, true);
        }
        return $log;
    }
    
    public static function logError($message, $context = []) {
        $logFile = self::getLogPath() . '/error.log';
        $timestamp = date(self::DATETIME_FORMAT);
        $contextStr = $context ? ' Context: ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] ERROR: {$message}{$contextStr}" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public static function logInfo($message, $context = []) {
        $logFile = self::getLogPath() . '/info.log';
        $timestamp = date(self::DATETIME_FORMAT);
        $contextStr = $context ? ' Context: ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] INFO: {$message}{$contextStr}" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

date_default_timezone_set(AppConfig::TIMEZONE);
?>