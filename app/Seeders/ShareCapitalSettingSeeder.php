<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class ShareCapitalSettingSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $settings = [
            [
                'sc_setting_01',
                'coop_01',
                100.00,
                100,
                25,
                10.00,
                100.00,
                'Subject to Board approval and 30-day prior written notice',
                'acc_3110',
            ],
        ];

        $sql = "
            INSERT INTO share_capital_settings (
                id,
                cooperative_id,
                par_value_per_share,
                min_subscription_shares,
                min_paid_up_shares,
                max_share_holding_percentage,
                transfer_fee,
                withdrawal_rule,
                accounting_account_id
            )
            VALUES (
                :id,
                :cooperative_id,
                :par_value_per_share,
                :min_subscription_shares,
                :min_paid_up_shares,
                :max_share_holding_percentage,
                :transfer_fee,
                :withdrawal_rule,
                :accounting_account_id
            )
            ON DUPLICATE KEY UPDATE
                cooperative_id = VALUES(cooperative_id),
                par_value_per_share = VALUES(par_value_per_share),
                min_subscription_shares = VALUES(min_subscription_shares),
                min_paid_up_shares = VALUES(min_paid_up_shares),
                max_share_holding_percentage = VALUES(max_share_holding_percentage),
                transfer_fee = VALUES(transfer_fee),
                withdrawal_rule = VALUES(withdrawal_rule),
                accounting_account_id = VALUES(accounting_account_id)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($settings as $setting) {
            $stmt->execute([
                ':id'                       => $setting[0],
                ':cooperative_id'           => $setting[1],
                ':par_value_per_share'     => $setting[2],
                ':min_subscription_shares' => $setting[3],
                ':min_paid_up_shares'      => $setting[4],
                ':max_share_holding_percentage' => $setting[5],
                ':transfer_fee'            => $setting[6],
                ':withdrawal_rule'         => $setting[7],
                ':accounting_account_id'   => $setting[8],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                scs.*,
                coa.account_code,
                coa.name AS gl_account_name
            FROM share_capital_settings scs
            LEFT JOIN chart_of_accounts coa
                ON scs.accounting_account_id = coa.id
            ORDER BY scs.cooperative_id ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}