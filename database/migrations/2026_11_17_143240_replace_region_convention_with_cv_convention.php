<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The free-text `region_convention` becomes a choice from the CvConvention enum (lookup table + FK). It is the
 * convention for English documents only: Portuguese documents always follow Brazilian conventions. Old values are
 * mapped (US, UK); EU, LATAM, BR and anything else become null, which means "International / EU".
 */
return new class extends Migration
{
    /** @var list<string> values in id order starting at 1 */
    private array $values = ['international', 'us', 'uk', 'brazil'];

    public function up(): void
    {
        Schema::create('cv_conventions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('value', 64)->unique();
        });

        DB::table('cv_conventions')->insert(array_map(
            static fn (string $value, int $index): array => ['id' => $index + 1, 'value' => $value],
            $this->values,
            array_keys($this->values),
        ));

        Schema::table('candidate_profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('cv_convention_id')->nullable()->after('work_authorization');
            $table->foreign('cv_convention_id')->references('id')->on('cv_conventions');
        });

        DB::table('candidate_profiles')->whereRaw('upper(region_convention) = ?', ['US'])->update(['cv_convention_id' => 2]);
        DB::table('candidate_profiles')->whereRaw('upper(region_convention) = ?', ['UK'])->update(['cv_convention_id' => 3]);

        Schema::table('candidate_profiles', function (Blueprint $table): void {
            $table->dropColumn('region_convention');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table): void {
            $table->string('region_convention', 16)->nullable();
        });

        DB::table('candidate_profiles')->where('cv_convention_id', 2)->update(['region_convention' => 'US']);
        DB::table('candidate_profiles')->where('cv_convention_id', 3)->update(['region_convention' => 'UK']);

        Schema::table('candidate_profiles', function (Blueprint $table): void {
            $table->dropForeign(['cv_convention_id']);
            $table->dropColumn('cv_convention_id');
        });

        Schema::dropIfExists('cv_conventions');
    }
};
