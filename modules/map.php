<?php
// ============================================================
//  modules/map.php — エリアマップ
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session.php';

function map_nodes(int $stage): array {
    $st          = STAGES[$stage];
    $inn_preview = rng($st['inn_cost'][0], $st['inn_cost'][1]);

    return [
        ['id'=>'fight',       'name'=>'ストリートファイト', 'tag'=>'[FIGHT]',  'desc'=>'モブと殴り合う。金が手に入る。',           'cost'=>null,                                           'danger'=>false],
        ['id'=>'inn',         'name'=>'宿',               'tag'=>'[REST]',   'desc'=>'金を払って全回復。',                        'cost'=>'¥'.number_format($inn_preview).'前後',         'danger'=>false],
        ['id'=>'dojo',        'name'=>'修練所',            'tag'=>'[TRAIN]',  'desc'=>'金をステータスへ変換。何回でも可。',          'cost'=>'¥'.$st['dojo_cost'].'/回',                    'danger'=>false],
        ['id'=>'weapon_shop', 'name'=>'武器屋',            'tag'=>'[WEAPON]', 'desc'=>'ランダム3品。重ね持ち可。ボス後没収。',       'cost'=>null,                                           'danger'=>false],
        ['id'=>'item_shop',   'name'=>'道具屋',            'tag'=>'[ITEM]',   'desc'=>'回復薬・補助アイテム。ボス後没収。',          'cost'=>null,                                           'danger'=>false],
        ['id'=>'boss',        'name'=>'AREA BOSS',        'tag'=>'[BOSS]',   'desc'=>'【'.$st['boss']['name'].'】HP:'.$st['boss']['hp'].' ATK:'.$st['boss']['atk'], 'cost'=>null, 'danger'=>true],
    ];
}
