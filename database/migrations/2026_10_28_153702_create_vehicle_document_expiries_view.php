<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vista di sola lettura sui documenti veicolo con scadenza compilata,
     * per la resource Filament "Scadenze Veicoli" (stesso meccanismo della
     * vista document_expiries usata per le scadenze autisti).
     */
    public function up(): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        $vehicleLabel = $isSqlite
            ? "COALESCE(v.plate, '') || CASE WHEN v.name IS NOT NULL AND v.name != '' THEN ' - ' || v.name ELSE '' END"
            : "CONCAT(COALESCE(v.plate, ''), CASE WHEN v.name IS NOT NULL AND v.name != '' THEN CONCAT(' - ', v.name) ELSE '' END)";

        DB::statement("
            CREATE VIEW vehicle_document_expiries AS
            SELECT vd.id AS id, vd.id AS vehicle_document_id, v.id AS vehicle_id,
                {$vehicleLabel} AS vehicle_label,
                COALESCE(vd.document_type, 'Documento') AS document_type,
                vd.expiry_date AS expiry_date
            FROM vehicle_documents vd
            INNER JOIN vehicles v ON v.id = vd.vehicle_id
            WHERE vd.expiry_date IS NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vehicle_document_expiries');
    }
};
