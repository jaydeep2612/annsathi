<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;

class BillPrintService
{
    /**
     * Render thermal receipt HTML according to paper size (58mm or 80mm) and print config.
     */
    public function generateReceiptHtml(Invoice $invoice): string
    {
        $restaurant = $invoice->restaurant;
        $config = $restaurant?->configuration;
        $order = $invoice->order;

        $paperSize = $config?->print_paper_size ?? 'thermal_80mm';
        $widthCss = ($paperSize === 'thermal_58mm') ? '58mm' : '80mm';

        // UPI QR URL generator string if UPI ID is present
        $upiQrData = null;
        if ($restaurant?->upi_id && ($config?->print_show_upi_qr ?? true)) {
            $upiQrData = sprintf(
                "upi://pay?pa=%s&pn=%s&am=%.2f&tn=Bill%%20%s&cu=INR",
                urlencode($restaurant->upi_id),
                urlencode($restaurant->name),
                $invoice->grand_total,
                urlencode($invoice->bill_number ?? $invoice->invoice_number)
            );
        }

        return view('receipts.thermal', [
            'invoice'       => $invoice,
            'order'         => $order,
            'restaurant'    => $restaurant,
            'config'        => $config,
            'widthCss'      => $widthCss,
            'taxBreakup'    => $invoice->tax_breakup ?? [],
            'upiQrData'     => $upiQrData,
        ])->render();
    }
}
