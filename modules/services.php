<?php
// ============================================================
//  modules/services.php — 宿 / 修練所 / 武器屋 / 道具屋
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session.php';

// ============================================================
//  宿
// ============================================================

function inn_quote(): int {
    $st = STAGES[player_get()['stage']];
    return rng($st['inn_cost'][0], $st['inn_cost'][1]);
}

function inn_stay(int $cost): array {
    $p = player_get();
    if ($p['money'] < $cost) {
        return ['ok' => false, 'lines' => ["> 金が足りない。(¥{$cost} 必要)"]];
    }
    $p['money'] -= $cost;
    $p['hp'] = $p['max_hp'];
    $p['mp'] = $p['max_mp'];
    player_set($p);
    return ['ok' => true, 'lines' => ["> ¥{$cost} を払って宿に泊まった。", "> HP・MP が全回復した。"], 'player' => $p];
}

// ============================================================
//  修練所
//  対象ステータス: HP → MP → ATK → DEF → AGI → LUK
// ============================================================

// 修練所で強化できるステータス定義（並び順もここで管理）
const DOJO_STATS = [
    ['key' => 'hp',  'label' => 'HP',  'note' => 'max_hp も同時に上昇'],
    ['key' => 'mp',  'label' => 'MP',  'note' => 'max_mp も同時に上昇'],
    ['key' => 'atk', 'label' => 'ATK', 'note' => ''],
    ['key' => 'def', 'label' => 'DEF', 'note' => ''],
    ['key' => 'agi', 'label' => 'AGI', 'note' => '回避・逃走に影響'],
    ['key' => 'luk', 'label' => 'LUK', 'note' => ''],
];

function dojo_cost(): int {
    return STAGES[player_get()['stage']]['dojo_cost'];
}

function dojo_stat_list(): array {
    return DOJO_STATS;
}

function dojo_train(string $stat): array {
    $p    = player_get();
    $cost = STAGES[$p['stage']]['dojo_cost'];

    $allowed = array_column(DOJO_STATS, 'key');
    if (!in_array($stat, $allowed, true)) {
        return ['ok' => false, 'lines' => ["> 無効なステータス。"]];
    }
    if ($p['money'] < $cost) {
        return ['ok' => false, 'lines' => ["> 金が足りない。(¥{$cost} 必要)"]];
    }

    $p['money'] -= $cost;
    $pct  = rng(1, 5);
    $gain = max(1, (int)round($p[$stat] * $pct / 100));
    $p[$stat] += $gain;

    // HP/MP は最大値も連動して上昇
    if ($stat === 'hp') $p['max_hp'] += $gain;
    if ($stat === 'mp') $p['max_mp'] += $gain;

    player_set($p);
    return [
        'ok'     => true,
        'lines'  => ["> 修練。".strtoupper($stat)." +{$gain}（+{$pct}%）。¥{$cost} 消費。"],
        'player' => $p,
    ];
}

// ============================================================
//  武器屋（CSV経由でマスター参照）
// ============================================================

function weapon_shop_stock(): array {
    if (!empty($_SESSION['weapon_stock'])) return $_SESSION['weapon_stock'];

    $pool = weapons_all();   // config.php の CSV読み込み関数
    shuffle($pool);
    $stock = [];
    foreach (array_slice($pool, 0, 3) as $w) {
        $stock[] = [
            'id'        => $w['id'],
            'name'      => $w['name'],
            'type'      => $w['type'],
            'desc'      => $w['desc'],
            'dmg'       => $w['dmg'],
            'throw_dmg' => $w['throw_dmg'],
            'price'     => rng($w['price'][0], $w['price'][1]),
        ];
    }
    $_SESSION['weapon_stock'] = $stock;
    return $stock;
}

function weapon_shop_refresh(): void { unset($_SESSION['weapon_stock']); }

function weapon_buy(int $idx): array {
    $stock = weapon_shop_stock();
    if (!isset($stock[$idx])) return ['ok' => false, 'lines' => ["> 無効な選択。"]];
    $item = $stock[$idx];
    $p    = player_get();
    if ($p['money'] < $item['price']) {
        return ['ok' => false, 'lines' => ["> 金が足りない。(¥{$item['price']} 必要)"]];
    }
    $p['money'] -= $item['price'];
    $p['weapons'][] = ['id' => $item['id'], 'name' => $item['name']];
    player_set($p);
    return [
        'ok'     => true,
        'lines'  => ["> [{$item['name']}] を購入した。¥{$item['price']} 消費。", "> 武装数: ".count($p['weapons'])],
        'player' => $p,
    ];
}

// ============================================================
//  道具屋（CSV経由でマスター参照）
// ============================================================

function item_shop_stock(): array {
    if (!empty($_SESSION['item_stock'])) return $_SESSION['item_stock'];

    $pool = items_all();   // config.php の CSV読み込み関数
    shuffle($pool);
    $stock = [];
    foreach (array_slice($pool, 0, 3) as $it) {
        $stock[] = [
            'id'     => $it['id'],
            'name'   => $it['name'],
            'effect' => $it['effect'],
            'value'  => $it['value'],
            'desc'   => $it['desc'],
            'price'  => rng($it['price'][0], $it['price'][1]),
        ];
    }
    $_SESSION['item_stock'] = $stock;
    return $stock;
}

function item_shop_refresh(): void { unset($_SESSION['item_stock']); }

function item_buy(int $idx): array {
    $stock = item_shop_stock();
    if (!isset($stock[$idx])) return ['ok' => false, 'lines' => ["> 無効な選択。"]];
    $item = $stock[$idx];
    $p    = player_get();
    if ($p['money'] < $item['price']) {
        return ['ok' => false, 'lines' => ["> 金が足りない。(¥{$item['price']} 必要)"]];
    }
    $p['money'] -= $item['price'];
    $p['items'][] = ['id' => $item['id'], 'name' => $item['name'], 'effect' => $item['effect'], 'value' => $item['value']];
    player_set($p);
    return [
        'ok'     => true,
        'lines'  => ["> [{$item['name']}] を購入した。¥{$item['price']} 消費。"],
        'player' => $p,
    ];
}
