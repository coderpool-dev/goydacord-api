<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** App\Enums\ServerPermission::CHANGE_NICKNAME — литерал, чтобы миграция не зависела от будущих правок enum. */
    private const CHANGE_NICKNAME = 1 << 19;

    public function up(): void
    {
        Schema::table('server_roles', function (Blueprint $table) {
            // Роль могут упоминать все, а не только те, у кого MENTION_EVERYONE.
            $table->boolean('mentionable')->default(false)->after('hoist');
        });

        Schema::table('messages', function (Blueprint $table) {
            // Кого сообщение реально упоминает — после проверки прав отправителя. Отдельно от текста:
            // текст зашифрован, а подсветка и «меня упомянули» нужны без расшифровки.
            $table->json('mentions')->nullable()->after('meta');
        });

        // Как в Discord: менять свой ник по умолчанию могут все — докидываем бит @everyone
        // существующих серверов (новые получают его из ServerPermission::DEFAULT).
        DB::table('server_roles')
            ->where('is_default', true)
            ->update(['permissions' => DB::raw('permissions | '.self::CHANGE_NICKNAME)]);
    }

    public function down(): void
    {
        DB::table('server_roles')
            ->where('is_default', true)
            ->update(['permissions' => DB::raw('permissions & ~'.self::CHANGE_NICKNAME)]);

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('mentions');
        });

        Schema::table('server_roles', function (Blueprint $table) {
            $table->dropColumn('mentionable');
        });
    }
};
