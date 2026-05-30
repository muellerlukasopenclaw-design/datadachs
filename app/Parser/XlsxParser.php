<?php
/**
 * DataDachs – XLSX Parser
 * Parst Excel-Dateien und pseudonymisiert PII in Zellen
 */

namespace DataDachs\Parser;

use DataDachs\Service\PiiDetector;
use DataDachs\Service\FakerEngine;
use DataDachs\Service\PreserveRuleService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class XlsxParser
{
    private PiiDetector $detector;
    private FakerEngine $faker;
    private ?PreserveRuleService $preserveService;
    
    public function __construct(PiiDetector $detector, FakerEngine $faker, ?PreserveRuleService $preserveService = null)
    {
        $this->detector = $detector;
        $this->faker = $faker;
        $this->preserveService = $preserveService;
    }
    
    /**
     * Prüft ob XLSX-Verarbeitung verfügbar ist
     */
    public static function isAvailable(): bool
    {
        return class_exists('ZipArchive');
    }
    
    /**
     * Analysiert ein XLSX auf PII-Vorkommen
     */
    public function analyze(string $filePath): array
    {
        if (!self::isAvailable()) {
            return [
                'file_type' => 'xlsx',
                'error' => 'ZIP-Extension nicht verfügbar. XLSX-Verarbeitung deaktiviert.',
                'total_sheets' => 0,
                'findings' => [],
            ];
        }
        
        $spreadsheet = IOFactory::load($filePath);
        $findings = [];
        
        foreach ($spreadsheet->getAllSheets() as $sheetIndex => $sheet) {
            $sheetName = $sheet->getTitle();
            $highestRow = $sheet->getHighestRow();
            $highestCol = $sheet->getHighestColumn();
            
            // Header-Zeile (Zeile 1) als Spaltennamen
            $headers = [];
            for ($col = 1; $col <= \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol); $col++) {
                $cellValue = $sheet->getCellByColumnAndRow($col, 1)->getValue();
                $headers[$col] = $cellValue ? (string) $cellValue : "Column{$col}";
            }
            
            // Erkennung pro Spalte
            $columnAnalysis = [];
            for ($col = 1; $col <= count($headers); $col++) {
                $columnName = $headers[$col];
                $detected = $this->detector->detectColumns($sheetName, [$columnName]);
                
                if ($detected[$columnName]['detected']) {
                    $columnAnalysis[$columnName] = $detected[$columnName];
                }
            }
            
            // Regex-Erkennung in allen Zellen
            for ($row = 2; $row <= $highestRow; $row++) {
                for ($col = 1; $col <= count($headers); $col++) {
                    $cell = $sheet->getCellByColumnAndRow($col, $row);
                    $value = $cell->getValue();
                    
                    if (!is_string($value)) {
                        continue;
                    }
                    
                    $valueDetection = $this->detector->detectValue($value);
                    if ($valueDetection) {
                        $findings[] = [
                            'sheet' => $sheetName,
                            'row' => $row,
                            'column' => $headers[$col],
                            'type' => $valueDetection['type'],
                            'value' => $value,
                            'method' => 'regex',
                            'action' => 'pseudonymize',
                        ];
                    }
                }
            }
            
            $findings[] = [
                'sheet' => $sheetName,
                'column_analysis' => $columnAnalysis,
                'row_count' => $highestRow - 1,
                'method' => 'header',
            ];
        }
        
        return [
            'file_type' => 'xlsx',
            'total_sheets' => $spreadsheet->getSheetCount(),
            'findings' => $findings,
        ];
    }
    
    /**
     * Pseudonymisiert ein XLSX-Dokument
     */
    public function pseudonymize(string $filePath, array $confirmedRules): string
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException('ZIP-Extension nicht verfügbar. XLSX-Verarbeitung deaktiviert.');
        }
        
        $spreadsheet = IOFactory::load($filePath);
        
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $highestRow = $sheet->getHighestRow();
            $highestCol = $sheet->getHighestColumn();
            
            // Header-Zeile
            $headers = [];
            for ($col = 1; $col <= \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol); $col++) {
                $cellValue = $sheet->getCellByColumnAndRow($col, 1)->getValue();
                $headers[$col] = $cellValue ? (string) $cellValue : "Column{$col}";
            }
            
            // Regeln für dieses Sheet
            $sheetName = $sheet->getTitle();
            $sheetRules = $confirmedRules[$sheetName] ?? [];
            
            // Zellen verarbeiten
            for ($row = 2; $row <= $highestRow; $row++) {
                for ($col = 1; $col <= count($headers); $col++) {
                    $columnName = $headers[$col];
                    $rule = $sheetRules[$columnName] ?? null;
                    
                    if (!$rule || ($rule['action'] ?? 'keep') !== 'pseudonymize') {
                        continue;
                    }
                    
                    $cell = $sheet->getCellByColumnAndRow($col, $row);
                    $value = $cell->getValue();
                    
                    if (!is_string($value) || empty($value)) {
                        continue;
                    }
                    
                    $type = $rule['type'];
                    
                    // Preserve Rules prüfen
                    if ($this->preserveService && $this->preserveService->shouldPreserve($value)) {
                        continue;
                    }
                    
                    $fakeValue = $this->faker->fake($type, $value);
                    $cell->setValue($fakeValue);
                }
            }
        }
        
        // Temporäre Datei speichern
        $tempPath = tempnam(sys_get_temp_dir(), 'datadachs_xlsx_') . '.xlsx';
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempPath);
        
        return $tempPath;
    }
    
    /**
     * Extrahiert Daten als CSV-String
     */
    public function toCsv(string $filePath, int $sheetIndex = 0): string
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheet($sheetIndex);
        
        $writer = IOFactory::createWriter($spreadsheet, 'Csv');
        $writer->setSheetIndex($sheetIndex);
        
        ob_start();
        $writer->save('php://output');
        return ob_get_clean();
    }
}
