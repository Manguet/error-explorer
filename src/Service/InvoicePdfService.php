<?php

namespace App\Service;

use App\Entity\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;
use Symfony\Component\Filesystem\Filesystem;

class InvoicePdfService
{
    public function __construct(
        private Environment $twig,
        private string $projectDir
    ) {}

    public function generateInvoicePdf(Invoice $invoice): string
    {
        // Configuration de DomPDF
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        // Générer le HTML de la facture
        $html = $this->twig->render('emails/invoice_pdf.html.twig', [
            'invoice' => $invoice,
            'user' => $invoice->getUser(),
            'subscription' => $invoice->getSubscription(),
            'plan' => $invoice->getPlan()
        ]);

        // Charger le HTML dans DomPDF
        $dompdf->loadHtml($html);

        // Configuration du papier (A4, portrait)
        $dompdf->setPaper('A4', 'portrait');

        // Rendre le PDF
        $dompdf->render();

        // Générer le nom de fichier
        $filename = sprintf(
            'invoice-%s-%s.pdf',
            $invoice->getNumber(),
            $invoice->getCreatedAt()->format('Y-m-d')
        );

        // Créer le répertoire s'il n'existe pas
        $invoiceDir = $this->projectDir . '/var/invoices';
        $filesystem = new Filesystem();
        $filesystem->mkdir($invoiceDir);

        // Chemin complet du fichier
        $filepath = $invoiceDir . '/' . $filename;

        // Sauvegarder le PDF
        file_put_contents($filepath, $dompdf->output());

        // Mettre à jour le chemin dans l'entité
        $invoice->setPdfPath('var/invoices/' . $filename);

        return $filepath;
    }

    public function getInvoicePdfContent(Invoice $invoice): string
    {
        $pdfPath = $invoice->getPdfPath();
        
        if (!$pdfPath) {
            // Générer le PDF s'il n'existe pas
            $this->generateInvoicePdf($invoice);
            $pdfPath = $invoice->getPdfPath();
        }

        $fullPath = $this->projectDir . '/' . $pdfPath;
        
        if (!file_exists($fullPath)) {
            // Régénérer le PDF s'il a été supprimé
            $this->generateInvoicePdf($invoice);
            $fullPath = $this->projectDir . '/' . $invoice->getPdfPath();
        }

        return file_get_contents($fullPath);
    }

    public function getInvoicePdfPath(Invoice $invoice): string
    {
        $pdfPath = $invoice->getPdfPath();
        
        if (!$pdfPath) {
            $this->generateInvoicePdf($invoice);
            $pdfPath = $invoice->getPdfPath();
        }

        $fullPath = $this->projectDir . '/' . $pdfPath;
        
        if (!file_exists($fullPath)) {
            $this->generateInvoicePdf($invoice);
            $fullPath = $this->projectDir . '/' . $invoice->getPdfPath();
        }

        return $fullPath;
    }

    public function deleteInvoicePdf(Invoice $invoice): bool
    {
        $pdfPath = $invoice->getPdfPath();
        
        if (!$pdfPath) {
            return true;
        }

        $fullPath = $this->projectDir . '/' . $pdfPath;
        
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        $invoice->setPdfPath(null);
        
        return true;
    }
}