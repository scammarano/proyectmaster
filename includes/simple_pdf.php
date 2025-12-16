<?php
/**
 * SimplePDF (very small)
 * - Writes basic text-only PDF (Helvetica) with automatic line wrap.
 * - Enough for "Informe" style reports. No images.
 */
class SimplePDF {
  private array $objects = [];
  private array $pages = [];
  private int $objIndex = 0;
  private string $fontName = 'Helvetica';
  private float $w = 595.28; // A4
  private float $h = 841.89;
  private float $margin = 36.0;
  private float $fontSize = 11.0;
  private float $leading = 14.0;

  public function setFontSize(float $pt): void { $this->fontSize = $pt; $this->leading = max(12.0, $pt*1.25); }

  private function newObj(string $content): int {
    $this->objIndex++;
    $this->objects[$this->objIndex] = $content;
    return $this->objIndex;
  }

  private function esc(string $s): string {
    $s = str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], $s);
    $s = preg_replace("/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/", "", $s);
    return $s;
  }

  public function addPage(array $lines, string $title=''): void {
    $this->pages[] = ['lines'=>$lines, 'title'=>$title];
  }

  private function wrapLine(string $text, int $maxChars): array {
    $text = trim($text);
    if ($text === '') return [''];
    $out = [];
    while (mb_strlen($text,'UTF-8') > $maxChars) {
      $chunk = mb_substr($text, 0, $maxChars, 'UTF-8');
      $pos = mb_strrpos($chunk, ' ', 0, 'UTF-8');
      if ($pos === false || $pos < 30) $pos = $maxChars;
      $out[] = trim(mb_substr($text, 0, $pos, 'UTF-8'));
      $text = trim(mb_substr($text, $pos, null, 'UTF-8'));
    }
    $out[] = $text;
    return $out;
  }

  public function output(string $filename='reporte.pdf'): void {
    // Catalog + font
    $fontObj = $this->newObj("<< /Type /Font /Subtype /Type1 /BaseFont /{$this->fontName} >>");

    $pageObjIds = [];
    foreach ($this->pages as $p) {
      $content = "BT\n/F1 {$this->fontSize} Tf\n";
      $x = $this->margin;
      $y = $this->h - $this->margin;
      $maxChars = 95; // rough wrap for A4
      if ($p['title']) {
        foreach ($this->wrapLine($p['title'], 80) as $tline) {
          $content .= sprintf("%.2f %.2f Td (%s) Tj\n", $x, $y, $this->esc($tline));
          $y -= ($this->leading + 4);
        }
        $y -= 4;
      }
      foreach ($p['lines'] as $line) {
        foreach ($this->wrapLine((string)$line, $maxChars) as $wline) {
          if ($y < $this->margin) { // stop page content
            break 2;
          }
          $content .= sprintf("%.2f %.2f Td (%s) Tj\n", $x, $y, $this->esc($wline));
          $y -= $this->leading;
        }
      }
      $content .= "ET\n";
      $contentStream = "<< /Length ".strlen($content)." >>\nstream\n{$content}\nendstream";
      $contentObj = $this->newObj($contentStream);

      $pageDict = "<< /Type /Page /Parent 0 0 R /MediaBox [0 0 {$this->w} {$this->h}] /Resources << /Font << /F1 {$fontObj} 0 R >> >> /Contents {$contentObj} 0 R >>";
      $pageObj = $this->newObj($pageDict);
      $pageObjIds[] = $pageObj;
    }

    // Pages tree
    $kids = implode(' ', array_map(fn($id)=>"$id 0 R", $pageObjIds));
    $pagesObj = $this->newObj("<< /Type /Pages /Kids [ {$kids} ] /Count ".count($pageObjIds)." >>");

    // Fix Parent references
    foreach ($pageObjIds as $pid) {
      $this->objects[$pid] = str_replace("0 0 R", "{$pagesObj} 0 R", $this->objects[$pid]);
    }

    $catalogObj = $this->newObj("<< /Type /Catalog /Pages {$pagesObj} 0 R >>");

    // Build PDF
    $pdf = "%PDF-1.4\n";
    $xref = [];
    $xref[0] = 0;
    foreach ($this->objects as $i=>$obj) {
      $xref[$i] = strlen($pdf);
      $pdf .= "{$i} 0 obj\n{$obj}\nendobj\n";
    }
    $startXref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($this->objects)+1)."\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i=1; $i<=count($this->objects); $i++) {
      $pdf .= sprintf("%010d 00000 n \n", $xref[$i]);
    }
    $pdf .= "trailer\n<< /Size ".(count($this->objects)+1)." /Root {$catalogObj} 0 R >>\nstartxref\n{$startXref}\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: '.strlen($pdf));
    echo $pdf;
    exit;
  }
}
