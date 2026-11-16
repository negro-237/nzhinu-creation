<?php

namespace App\Services;

use App\Models\Order;
use Carbon\CarbonInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

class OrderReceiptPdf
{
    public function render(Order $order, CarbonInterface $issuedAt, string $trackingUrl): string
    {
        $directory = storage_path('framework/cache/receipt-pdf');
        File::ensureDirectoryExists($directory);
        $options = new Options;
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setIsFontSubsettingEnabled(true);
        $options->setChroot(resource_path('views/pdf'));
        $options->setTempDir($directory);
        $options->setFontCache($directory);

        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml(view('pdf.orders.receipt', [
            'order' => $order->loadMissing(['items', 'fulfillment']),
            'issuedAt' => $issuedAt,
            'trackingUrl' => $trackingUrl,
        ])->render(), 'UTF-8');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(40, $canvas->get_height() - 25, $order->number, $font, 8, [0.4, 0.4, 0.4]);
        $canvas->page_text(470, $canvas->get_height() - 25, 'Page {PAGE_NUM} / {PAGE_COUNT}', $font, 8, [0.4, 0.4, 0.4]);

        return $pdf->output();
    }
}
