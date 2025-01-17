<?php

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("Acesso não permitido.");
}

/**
 * Hook InvoiceCreated - Associa um afiliado ao pedido caso um cupom tenha sido usado.
 */
add_hook('InvoiceCreated', 1, function ($vars) {
    $invoiceId = $vars['invoiceid'];

    error_log("DEBUG: InvoiceCreated Hook acionado para Invoice ID: $invoiceId");

    // Buscar o UserID da fatura
    $userId = Capsule::table('tblinvoices')
        ->where('id', $invoiceId)
        ->value('userid');

    if (!$userId) {
        error_log("ERRO: Nenhum usuário encontrado para Invoice ID: $invoiceId");
        return;
    }

    error_log("DEBUG: UserID da fatura: $userId");

    // Buscar o pedido correspondente ao usuário e ao cupom
    $order = Capsule::table('tblorders')
        ->where('userid', $userId)
        ->orderBy('id', 'desc') // Pega o pedido mais recente desse usuário
        ->first();

    if (!$order || empty($order->promocode)) {
        error_log("ERRO: Nenhum cupom encontrado para o usuário ID $userId");
        return;
    }

    $promoCode = $order->promocode;
    error_log("DEBUG: Cupom usado: $promoCode");

    // Buscar o afiliado associado ao cupom na tabela tblpromotions
    $affiliateId = Capsule::table('tblpromotions')
        ->where('code', $promoCode)
        ->value('affiliateid');

    if (!$affiliateId) {
        error_log("DEBUG: Nenhum afiliado associado ao cupom '$promoCode'");
        return;
    }

    error_log("DEBUG: Afiliado encontrado: ID $affiliateId");

    // Buscar o `relid` correto nos itens da fatura
    $relId = Capsule::table('tblinvoiceitems')
        ->where('invoiceid', $invoiceId)
        ->orderBy('id', 'asc') // Pega o primeiro item relacionado à fatura
        ->value('relid');

    if (!$relId) {
        error_log("ERRO: Nenhum relid encontrado para Invoice ID: $invoiceId");
        return;
    }

    error_log("DEBUG: relid correto encontrado: $relId");

    // Verificar se o pedido já está associado ao afiliado
    $existingReferral = Capsule::table('tblaffiliatesaccounts')
        ->where('relid', $relId) // Agora usamos `relid` correto
        ->where('affiliateid', $affiliateId)
        ->exists();

    if ($existingReferral) {
        error_log("DEBUG: O pedido já está associado ao afiliado ID $affiliateId.");
        return;
    }

    try {
        Capsule::table('tblaffiliatesaccounts')->insert([
            'affiliateid' => $affiliateId,
            'relid' => $relId // Agora usamos o `relid` correto
        ]);

        error_log("SUCESSO: Pedido $relId associado ao afiliado $affiliateId via cupom '$promoCode'.");
    } catch (Exception $e) {
        error_log("ERRO: Falha ao associar pedido $relId ao afiliado: " . $e->getMessage());
    }
});