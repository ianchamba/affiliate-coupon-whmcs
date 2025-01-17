<?php

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("Acesso não permitido.");
}

/**
 * Configuração do módulo
 */
function AffiliateCoupons_config() {
    return [
        "name" => "Affiliate Coupons",
        "description" => "Permite associar cupons de desconto a afiliados.",
        "version" => "1.1",
        'author' => '<a href="https://hostbraza.com.br" target="_blank" style="text-decoration:none;color:#007bff;">Hostbraza</a>',
        "language" => "portuguese-br",
    ];
}

/**
 * Ativar o módulo
 */
function AffiliateCoupons_activate() {
    try {
        Capsule::schema()->table('tblpromotions', function ($table) {
            if (!Capsule::schema()->hasColumn('tblpromotions', 'affiliateid')) {
                $table->integer('affiliateid')->nullable()->default(null)->after('id');
            }
        });

        return ['status' => 'success', 'description' => 'Módulo ativado com sucesso.'];
    } catch (Exception $e) {
        return ['status' => 'error', 'description' => 'Erro ao ativar: ' . $e->getMessage()];
    }
}

/**
 * Desativar o módulo
 */
function AffiliateCoupons_deactivate() {
    return ['status' => 'success', 'description' => 'Módulo desativado.'];
}

/**
 * Interface do módulo no admin
 */
function AffiliateCoupons_output($vars) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coupon_id']) && isset($_POST["affiliate_id_{$_POST['coupon_id']}"])) {
        $couponId = (int) $_POST['coupon_id'];
        $affiliateId = ($_POST["affiliate_id_$couponId"] !== '') ? (int) $_POST["affiliate_id_$couponId"] : null;

        // Debug: Verificar os valores recebidos
        echo "<pre>DEBUG: Coupon ID: $couponId | Affiliate ID: " . ($affiliateId ?? 'NULL') . "</pre>";

        try {
            $updatedRows = Capsule::table('tblpromotions')
                ->where('id', $couponId)
                ->update(['affiliateid' => $affiliateId]);

            if ($updatedRows > 0) {
                echo '<div class="alert alert-success">Afiliado atualizado com sucesso para o cupom!</div>';
            } else {
                echo '<div class="alert alert-warning">Nenhuma linha foi alterada. Verifique se o cupom selecionado é válido.</div>';
            }
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">Erro ao atualizar: ' . $e->getMessage() . '</div>';
        }
    }

    // Buscar cupons e afiliados
    $coupons = Capsule::table('tblpromotions')->get();
    $affiliates = Capsule::table('tblaffiliates')
        ->join('tblclients', 'tblaffiliates.clientid', '=', 'tblclients.id')
        ->select('tblaffiliates.id as affiliate_id', 'tblclients.firstname', 'tblclients.lastname')
        ->get();

    echo '<h2>Gerenciar Cupons e Afiliados</h2>';
    echo '<form method="post">';
    echo '<table class="table table-bordered">';
    echo '<tr><th>Cupom</th><th>Afiliado</th><th>Ação</th></tr>';

    foreach ($coupons as $coupon) {
        echo '<tr>';
        echo "<td>{$coupon->code}</td>";
        echo '<td>';
        echo "<select name=\"affiliate_id_{$coupon->id}\" class=\"form-control\">";
        echo '<option value="">Nenhum</option>';
        
        foreach ($affiliates as $affiliate) {
            $selected = ($coupon->affiliateid == $affiliate->affiliate_id) ? 'selected' : '';
            echo "<option value=\"{$affiliate->affiliate_id}\" $selected>{$affiliate->firstname} {$affiliate->lastname}</option>";
        }
        
        echo '</select>';
        echo '</td>';
        echo "<td><button type='submit' name='coupon_id' value='{$coupon->id}' class='btn btn-primary'>Salvar</button></td>";
        echo '</tr>';
    }

    echo '</table>';
    echo '</form>';
}