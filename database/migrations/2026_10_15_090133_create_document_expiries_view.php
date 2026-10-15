<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vista di sola lettura che trasforma le 4 colonne di scadenza di
     * user_verifications (una riga per utente) in una riga per documento,
     * per poterle mostrare/filtrare/ordinare singolarmente nella resource
     * Filament "Scadenze" senza materializzare una tabella separata.
     */
    public function up(): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        $concatNames = $isSqlite
            ? "u.name || ' ' || u.surname"
            : "CONCAT(u.name, ' ', u.surname)";

        $idFor = fn (string $suffix): string => $isSqlite
            ? "uv.id || '-{$suffix}'"
            : "CONCAT(uv.id, '-{$suffix}')";

        DB::statement("
            CREATE VIEW document_expiries AS
            SELECT {$idFor('identity')} AS id, uv.id AS user_verification_id, u.id AS user_id,
                {$concatNames} AS user_full_name,
                'identity' AS document_type, 'Documento di identita' AS document_label,
                uv.identity_expiry_date AS expiry_date
            FROM user_verifications uv
            INNER JOIN users u ON u.id = uv.user_id
            WHERE uv.identity_expiry_date IS NOT NULL

            UNION ALL

            SELECT {$idFor('license')} AS id, uv.id AS user_verification_id, u.id AS user_id,
                {$concatNames} AS user_full_name,
                'license' AS document_type, 'Patente' AS document_label,
                uv.license_expiry_date AS expiry_date
            FROM user_verifications uv
            INNER JOIN users u ON u.id = uv.user_id
            WHERE uv.license_expiry_date IS NOT NULL

            UNION ALL

            SELECT {$idFor('cqc')} AS id, uv.id AS user_verification_id, u.id AS user_id,
                {$concatNames} AS user_full_name,
                'cqc' AS document_type, 'CQC' AS document_label,
                uv.cqc_expiry_date AS expiry_date
            FROM user_verifications uv
            INNER JOIN users u ON u.id = uv.user_id
            WHERE uv.cqc_expiry_date IS NOT NULL

            UNION ALL

            SELECT {$idFor('tachograph')} AS id, uv.id AS user_verification_id, u.id AS user_id,
                {$concatNames} AS user_full_name,
                'tachograph' AS document_type, 'Carta tachigrafica' AS document_label,
                uv.tachograph_expiry_date AS expiry_date
            FROM user_verifications uv
            INNER JOIN users u ON u.id = uv.user_id
            WHERE uv.tachograph_expiry_date IS NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS document_expiries');
    }
};
