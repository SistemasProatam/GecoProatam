<?php
require_once __DIR__ . "/../config.php";
/**
 * QR Generator – PROATAM
 * Genera códigos QR utilizando phpqrcode y fallback con GD.
 * Compatible con PHP 7.4+
 */

class QRGenerator
{
    const QR_DIR = '/uploads/qrcodes/';

    // ─────────────────────────────────────────
    // GENERAR TOKEN
    // ─────────────────────────────────────────

    public static function generarToken(): string
    {
        return strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    // ─────────────────────────────────────────
    // URL DEL QR
    // ─────────────────────────────────────────

    public static function buildScanUrl(string $token): string
    {
        $base = "https://proatamgoc.duckdns.org";

        return $base . "/activos/scan_activo.php?token=" . urlencode($token);
    }

    // ─────────────────────────────────────────
    // GENERAR QR Y GUARDAR
    // ─────────────────────────────────────────

    public static function generarYGuardar(string $token): ?string
    {
        $dir = __DIR__ . '/..' . self::QR_DIR;

        $nombre = 'qr_' . $token . '.png';

        $ruta = $dir . $nombre;

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        if (file_exists($ruta) && filesize($ruta) > 0) {
            return self::QR_DIR . $nombre;
        }

        $url = self::buildScanUrl($token);

        // ───── Cargar phpqrcode ─────

        $qrlib = __DIR__ . '/../libs/phpqrcode/qrlib.php';

        if (file_exists($qrlib)) {
            require_once $qrlib;
        }

        // ───── Generar QR con phpqrcode ─────

        if (class_exists('QRcode')) {

            QRcode::png(
                $url,
                $ruta,
                QR_ECLEVEL_H,
                5,
                3
            );

            if (file_exists($ruta)) {
                return self::QR_DIR . $nombre;
            }
        }

        // ───── Fallback con GD ─────

        if (extension_loaded('gd')) {

            $ok = self::guardarPNG($url, $ruta, 8);

            if ($ok && file_exists($ruta)) {
                return self::QR_DIR . $nombre;
            }
        }

        return null;
    }

    // ─────────────────────────────────────────
    // OBTENER URL DEL QR
    // ─────────────────────────────────────────

    public static function getQRUrl(string $token, ?string $saved, int $size = 300): string
    {

        // 1. Si existe archivo guardado

        if ($saved && file_exists(__DIR__ . '/..' . $saved)) {
            return $saved;
        }

        // 2. Intentar generarlo

        $nuevo = self::generarYGuardar($token);

        if ($nuevo && file_exists(__DIR__ . '/..' . $nuevo)) {
            return $nuevo;
        }

        // 3. Generar en memoria con phpqrcode

        $qrlib = __DIR__ . '/../libs/phpqrcode/qrlib.php';

        if (file_exists($qrlib) && !class_exists('QRcode')) {
            require_once $qrlib;
        }

        if (class_exists('QRcode')) {

            ob_start();

            QRcode::png(
                self::buildScanUrl($token),
                false,
                QR_ECLEVEL_H,
                10,
                2
            );

            $bytes = ob_get_clean();

            if ($bytes) {
                return 'data:image/png;base64,' . base64_encode($bytes);
            }
        }

        // 4. Fallback GD

        if (extension_loaded('gd')) {
            return self::dataUri(self::buildScanUrl($token), $size);
        }

        return '';
    }

    // ─────────────────────────────────────────
    // ELIMINAR QR
    // ─────────────────────────────────────────

    public static function eliminar(string $token): void
    {
        $ruta = __DIR__ . '/..' . self::QR_DIR . 'qr_' . $token . '.png';

        if (file_exists($ruta)) {
            unlink($ruta);
        }
    }

    // ─────────────────────────────────────────
    // GENERADOR SIMPLE GD
    // ─────────────────────────────────────────

    private static function guardarPNG(string $data, string $dest, int $px): bool
    {

        $size = 300;

        $img = imagecreatetruecolor($size, $size);

        if (!$img) return false;

        $blanco = imagecolorallocate($img, 255, 255, 255);

        $negro = imagecolorallocate($img, 0, 0, 0);

        imagefilledrectangle($img, 0, 0, $size, $size, $blanco);

        imagestring($img, 3, 20, 140, "QR ERROR", $negro);

        $ok = imagepng($img, $dest);

        imagedestroy($img);

        return $ok;
    }

    // ─────────────────────────────────────────
    // DATA URI GD
    // ─────────────────────────────────────────

    private static function dataUri(string $data, int $size): string
    {

        $img = imagecreatetruecolor($size, $size);

        if (!$img) return '';

        $blanco = imagecolorallocate($img, 255, 255, 255);

        $negro = imagecolorallocate($img, 0, 0, 0);

        imagefilledrectangle($img, 0, 0, $size, $size, $blanco);

        imagestring($img, 3, 20, 60, "QR", $negro);

        ob_start();

        imagepng($img);

        $bytes = ob_get_clean();

        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode($bytes);
    }
}

