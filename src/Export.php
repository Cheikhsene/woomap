<?php
/**
 * Export functionality.
 * Generates CSV exports and printable HTML reports.
 */

if (!defined('WOOMAP')) exit;

class Export
{
    /**
     * Export orders to CSV and send as download.
     */
    public static function toCSV(array $orders, string $filename = 'woomap-export.csv'): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM for Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");

        // Header row
        fputcsv($output, [
            'ID Commande',
            'Date',
            'Statut',
            'Client',
            'Email',
            'Téléphone',
            'Adresse',
            'Ville',
            'Montant',
            'Devise',
            'Produits',
        ], ';');

        // Data rows
        foreach ($orders as $order) {
            $products = array_map(function ($item) {
                return $item['name'] . ' x' . $item['quantity'];
            }, $order['items'] ?? []);

            fputcsv($output, [
                $order['id'],
                $order['date'] ? date('d/m/Y H:i', strtotime($order['date'])) : '',
                self::translateStatus($order['status']),
                $order['customer'],
                $order['email'],
                $order['phone'],
                $order['address'],
                $order['city'],
                number_format($order['total'], 0, ',', ' '),
                $order['currency'] ?? 'XOF',
                implode(', ', $products),
            ], ';');
        }

        fclose($output);
    }

    /**
     * Generate a printable HTML report.
     */
    public static function toPrintHTML(array $orders, array $stats): string
    {
        $storeName = '';
        $store = Store::getActive();
        if ($store) {
            $storeName = htmlspecialchars($store['name']);
        }

        $date = date('d/m/Y H:i');
        $totalOrders = count($orders);
        $totalSales = number_format($stats['total_sales'] ?? 0, 0, ',', ' ');

        $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport WooMap - ' . $storeName . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 12px; color: #1a1a1a; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #2563eb; padding-bottom: 15px; }
        .header h1 { font-size: 22px; color: #2563eb; }
        .header p { color: #666; margin-top: 5px; }
        .stats { display: flex; gap: 20px; margin-bottom: 25px; justify-content: center; }
        .stat-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 20px; border-radius: 6px; text-align: center; }
        .stat-box .value { font-size: 20px; font-weight: 700; color: #2563eb; }
        .stat-box .label { font-size: 11px; color: #64748b; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #2563eb; color: white; padding: 8px 10px; text-align: left; font-size: 11px; text-transform: uppercase; }
        td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) { background: #f8fafc; }
        .status { padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-processing { background: #fef3c7; color: #92400e; }
        .status-cancelled { background: #fecaca; color: #991b1b; }
        .footer { margin-top: 30px; text-align: center; color: #94a3b8; font-size: 10px; border-top: 1px solid #e2e8f0; padding-top: 10px; }
        @media print { body { padding: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:center;margin-bottom:20px;">
        <button onclick="window.print()" style="padding:10px 30px;background:#2563eb;color:white;border:none;border-radius:6px;cursor:pointer;font-size:14px;">Imprimer / PDF</button>
    </div>
    <div class="header">
        <h1>' . APP_NAME . ' - Rapport des commandes</h1>
        <p>' . $storeName . ' | Généré le ' . $date . '</p>
    </div>
    <div class="stats">
        <div class="stat-box"><div class="value">' . $totalOrders . '</div><div class="label">Commandes</div></div>
        <div class="stat-box"><div class="value">' . $stats['completed'] . '</div><div class="label">Complétées</div></div>
        <div class="stat-box"><div class="value">' . $stats['processing'] . '</div><div class="label">En cours</div></div>
        <div class="stat-box"><div class="value">' . $totalSales . ' FCFA</div><div class="label">Ventes totales</div></div>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Client</th>
                <th>Ville</th>
                <th>Statut</th>
                <th>Montant</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($orders as $order) {
            $statusClass = 'status-' . ($order['status'] ?? 'other');
            $html .= '<tr>
                <td>#' . htmlspecialchars($order['id']) . '</td>
                <td>' . ($order['date'] ? date('d/m/Y', strtotime($order['date'])) : '-') . '</td>
                <td>' . htmlspecialchars($order['customer']) . '</td>
                <td>' . htmlspecialchars($order['city']) . '</td>
                <td><span class="status ' . $statusClass . '">' . self::translateStatus($order['status']) . '</span></td>
                <td>' . number_format($order['total'], 0, ',', ' ') . ' FCFA</td>
            </tr>';
        }

        $html .= '</tbody>
    </table>
    <div class="footer">
        <p>' . APP_NAME . ' v' . APP_VERSION . ' | Rapport auto-généré</p>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Translate order status to French.
     */
    public static function translateStatus(string $status): string
    {
        $translations = [
            'completed' => 'Complétée',
            'processing' => 'En cours',
            'cancelled' => 'Annulée',
            'pending' => 'En attente',
            'on-hold' => 'En pause',
            'refunded' => 'Remboursée',
            'failed' => 'Échouée',
            'trash' => 'Supprimée',
        ];
        return $translations[$status] ?? ucfirst($status);
    }
}
