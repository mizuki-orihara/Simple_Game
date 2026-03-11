<?php
// ============================================================
//  config.php — マスターデータ・定数
//  武器/アイテムは data/*.csv から読み込む
// ============================================================

define('GAME_TITLE', 'STREET DOGS');
define('SESSION_KEY', 'sd_player');
define('DATA_DIR', __DIR__ . '/data');

// ステージ定義（変更頻度低いのでPHP直書き）
define('STAGES', [
    1 => [
        'name'      => '路地裏',
        'sub'       => 'Back Alley',
        'mob_hp'    => [20, 60],
        'mob_atk'   => [8, 18],
        'mob_def'   => [2, 8],
        'inn_cost'  => [30, 80],
        'dojo_cost' => 50,
        'boss' => [
            'name' => '裏番長 CROW',
            'hp'   => 200,
            'atk'  => 28,
            'def'  => 10,
        ],
    ],
    2 => [
        'name'      => '繁華街',
        'sub'       => 'Downtown',
        'mob_hp'    => [50, 120],
        'mob_atk'   => [18, 30],
        'mob_def'   => [6, 14],
        'inn_cost'  => [80, 180],
        'dojo_cost' => 100,
        'boss' => [
            'name' => '地区ボス SNAKE',
            'hp'   => 350,
            'atk'  => 42,
            'def'  => 18,
        ],
    ],
    3 => [
        'name'      => 'ドック',
        'sub'       => 'The Docks',
        'mob_hp'    => [100, 200],
        'mob_atk'   => [28, 45],
        'mob_def'   => [12, 22],
        'inn_cost'  => [150, 300],
        'dojo_cost' => 180,
        'boss' => [
            'name' => '港湾王 KRAKEN',
            'hp'   => 550,
            'atk'  => 60,
            'def'  => 28,
        ],
    ],
]);

// モブ名プール
define('MOB_NAMES', [
    'チンピラ','ゴロツキ','用心棒','流れ者','不良',
    '喧嘩屋','ならず者','組の下っ端','スリ師','酔っ払い',
    '元ボクサー','刺青野郎','ヤク売り','見張り番','荒くれ者',
]);

// ============================================================
//  CSV 読み込みキャッシュ（リクエスト内メモリキャッシュ）
// ============================================================
$_MASTER_CACHE = [];

/**
 * CSVを連想配列の配列として返す（ヘッダ行をキーに使用）
 */
function csv_load(string $filename): array {
    global $_MASTER_CACHE;
    if (isset($_MASTER_CACHE[$filename])) {
        return $_MASTER_CACHE[$filename];
    }

    $path = DATA_DIR . '/' . $filename;
    if (!file_exists($path)) {
        trigger_error("CSV not found: {$path}", E_USER_WARNING);
        return [];
    }

    $rows = [];
    $fh   = fopen($path, 'r');
    $headers = fgetcsv($fh);  // 1行目はヘッダ

    while (($row = fgetcsv($fh)) !== false) {
        // 空行スキップ
        if (count($row) < 2 || trim($row[0]) === '') continue;
        $rows[] = array_combine($headers, $row);
    }
    fclose($fh);

    $_MASTER_CACHE[$filename] = $rows;
    return $rows;
}

/**
 * 武器マスター全件
 */
function weapons_all(): array {
    $rows = csv_load('weapons.csv');
    return array_map(function($r) {
        return [
            'id'        => $r['id'],
            'name'      => $r['name'],
            'type'      => $r['type'],
            'dmg'       => [(int)$r['dmg_min'], (int)$r['dmg_max']],
            'throw_dmg' => ($r['throw_dmg_min'] !== '') ? [(int)$r['throw_dmg_min'], (int)$r['throw_dmg_max']] : null,
            'price'     => [(int)$r['price_min'], (int)$r['price_max']],
            'desc'      => $r['desc'],
        ];
    }, $rows);
}

/**
 * アイテムマスター全件
 */
function items_all(): array {
    $rows = csv_load('items.csv');
    return array_map(function($r) {
        return [
            'id'     => $r['id'],
            'name'   => $r['name'],
            'effect' => $r['effect'],
            'value'  => (int)$r['value'],
            'price'  => [(int)$r['price_min'], (int)$r['price_max']],
            'desc'   => $r['desc'],
        ];
    }, $rows);
}

/**
 * ID指定で武器1件取得
 */
function get_weapon(string $id): ?array {
    foreach (weapons_all() as $w) {
        if ($w['id'] === $id) return $w;
    }
    return null;
}

/**
 * ID指定でアイテム1件取得
 */
function get_item(string $id): ?array {
    foreach (items_all() as $it) {
        if ($it['id'] === $id) return $it;
    }
    return null;
}

// ============================================================
//  共通ユーティリティ
// ============================================================
function rng(int $min, int $max): int {
    return random_int($min, $max);
}
