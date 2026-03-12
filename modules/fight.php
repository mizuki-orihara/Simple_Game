<?php
// ============================================================
//  modules/fight.php ? 戦闘エンジン
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session.php';

function fight_init(bool $is_boss = false): array {
    $p  = player_get();
    $st = STAGES[$p['stage']];

    if ($is_boss) {
        $mob = [
            'name'    => $st['boss']['name'],
            'hp'      => $st['boss']['hp'],
            'max_hp'  => $st['boss']['hp'],
            'atk'     => $st['boss']['atk'],
            'def'     => $st['boss']['def'],
            'is_boss' => true,
        ];
    } else {
        $hp  = rng($st['mob_hp'][0], $st['mob_hp'][1]);
        $mob = [
            'name'    => MOB_NAMES[rng(0, count(MOB_NAMES) - 1)],
            'hp'      => $hp,
            'max_hp'  => $hp,
            'atk'     => rng($st['mob_atk'][0], $st['mob_atk'][1]),
            'def'     => rng($st['mob_def'][0], $st['mob_def'][1]),
            'is_boss' => false,
        ];
    }

    $p['battle'] = $mob;
    player_set($p);
    return $mob;
}

function fight_action(string $action): array {
    $p   = player_get();
    $mob = $p['battle'];
    $lines  = [];
    $result = 'continue';

    $atk = eff_atk($p);
    $def = eff_def($p);

    $player_dmg = 0;
    $defending  = false;

    switch ($action) {

        case 'attack':
            $weapon_bonus = _weapon_melee_bonus($p);
            $player_dmg   = max(1, rng($atk - 4, $atk + 6) + $weapon_bonus);
            $lines[] = "> 殴りかかった！ [{$mob['name']}] に {$player_dmg} ダメージ。";
            [$p, $throw_lines] = _consume_throw_weapons($p);
            $lines = array_merge($lines, $throw_lines);
            break;

        case 'throw':
            [$p, $throw_dmg, $throw_lines] = _do_throw($p);
            $player_dmg = $throw_dmg;
            $lines = array_merge($lines, $throw_lines);
            break;

        case 'skill':
            if ($p['mp'] < 10) { $lines[] = "> MPが足りない。"; break; }
            $p['mp'] -= 10;
            $player_dmg = max(1, rng($atk, $atk * 2));
            $lines[] = "> スキル発動！ MP-10。[{$mob['name']}] に {$player_dmg} ダメージ！";
            break;

        case 'defend':
            $defending = true;
            $lines[] = "> 防御構え。";
            break;

        case 'item':
            if (empty($p['items'])) { $lines[] = "> アイテムがない。"; break; }
            $item = array_shift($p['items']);
            [$p, $item_lines] = _use_item($p, $item);
            $lines = array_merge($lines, $item_lines);
            break;

        case 'run':
            if (!empty($mob['is_boss'])) { $lines[] = "> ボスから逃げることはできない。"; break; }
            $smoke_idx = _find_item_idx($p, 'escape');
            if ($smoke_idx !== false) {
                array_splice($p['items'], $smoke_idx, 1);
                $lines[] = "> スモークボムを使って逃走した！";
                $p['battle'] = null;
                $p = advance_day($p);   // 戦闘終了（逃走）で1日経過
                player_set($p);
                return ['lines' => $lines, 'result' => 'escape', 'player' => $p];
            }
            $chance = 30 + $p['agi'] - $mob['atk'];
            if (rng(1, 100) <= max(10, $chance)) {
                $lines[] = "> 逃げ出した！";
                $p['battle'] = null;
                $p = advance_day($p);   // 戦闘終了（逃走）で1日経過
                player_set($p);
                return ['lines' => $lines, 'result' => 'escape', 'player' => $p];
            }
            $lines[] = "> 逃走失敗！";
            break;
    }

    if ($player_dmg > 0) {
        $mob['hp'] = max(0, $mob['hp'] - $player_dmg);
    }

    // 勝利判定
    if ($mob['hp'] <= 0) {
        $result  = 'win';
        $reward  = rng(10, 80) + ($p['stage'] - 1) * 20;
        // 金運の護符が有効なら報酬×2
        if (!empty($p['gold_fever_days'])) {
            $reward *= 2;
            $lines[] = "> 【金運の護符】効果中！ (残{$p['gold_fever_days']}日)";
        }
        $p['money']    += $reward;
        $p['temp_atk']  = 0;
        $p['temp_def']  = 0;
        $lines[] = "> [{$mob['name']}] を倒した！";
        $lines[] = "> \{$reward} を手に入れた。";

        if (!empty($mob['is_boss'])) {
            $result  = 'boss_win';
            $lines[] = "> ===========================";
            $lines[] = "> BOSS [{$mob['name']}] 撃破！";
            $lines[] = "> 武器・アイテムは没収された。";
            $lines[] = "> ステータスは裸で持ち越す。";
            $p['weapons'] = [];
            $p['items']   = [];
            $p['stage']++;
            if ($p['stage'] > count(STAGES)) {
                $result  = 'game_clear';
                $lines[] = "> ===========================";
                $lines[] = "> 全ステージ制覇。";
                $lines[] = "> お前が路地裏の王だ。";
            } else {
                $p['hp'] = $p['max_hp'];
                $p['mp'] = $p['max_mp'];
                $lines[] = "> ステージ " . $p['stage'] . " へ。";
            }
        }

        $p['battle'] = null;
        $p = advance_day($p);   // 戦闘終了（勝利）で1日経過
        player_set($p);
        return ['lines' => $lines, 'result' => $result, 'player' => $p];
    }

    // 敵の反撃
    if ($action !== 'run') {
        $raw = max(0, $mob['atk'] - $def + rng(-4, 4));
        if ($defending) $raw = (int)($raw / 2);
        if (rng(1, 100) <= (int)($p['agi'] / 2)) {
            $lines[] = "> 素早く回避した！";
        } else {
            if ($raw > 0) {
                $lines[] = "> [{$mob['name']}] の攻撃！ {$raw} ダメージ。";
                $p['hp']  = max(0, $p['hp'] - $raw);
            } else {
                $lines[] = "> [{$mob['name']}] の攻撃を完全に弾いた。";
            }
        }
    }

    if ($p['hp'] <= 0) {
        $lines[] = "> 力尽きた……";
        $p['battle'] = null;
        player_set($p);
        return ['lines' => $lines, 'result' => 'lose', 'player' => $p];
    }

    $p['battle'] = $mob;
    player_set($p);
    return ['lines' => $lines, 'result' => $result, 'player' => $p, 'mob' => $mob];
}

// ---- 内部ヘルパー ----

function _weapon_melee_bonus(array $p): int {
    $bonus = 0;
    foreach ($p['weapons'] as $w) {
        $m = get_weapon($w['id']);
        if ($m && $m['dmg'][1] > 0) $bonus += rng($m['dmg'][0], $m['dmg'][1]);
    }
    return $bonus;
}

function _consume_throw_weapons(array $p): array {
    $lines = [];
    $keep  = [];
    foreach ($p['weapons'] as $w) {
        $m = get_weapon($w['id']);
        if ($m && $m['type'] === 'throw') {
            $lines[] = "> [{$w['name']}] 自動投擲 → 消費した。";
        } else {
            $keep[] = $w;
        }
    }
    $p['weapons'] = $keep;
    return [$p, $lines];
}

function _do_throw(array $p): array {
    $lines = [];
    $dmg   = 0;
    foreach ($p['weapons'] as $i => $w) {
        $m = get_weapon($w['id']);
        if ($m && $m['type'] === 'throw') {
            $td  = $m['throw_dmg'] ?? $m['dmg'];
            $dmg = rng($td[0], $td[1]);
            $lines[] = "> [{$w['name']}] 投擲！ {$dmg} ダメージ。消費した。";
            array_splice($p['weapons'], $i, 1);
            break;
        }
    }
    if ($dmg === 0 && empty($lines)) $lines[] = "> 投擲できる武器がない。";
    return [$p, $dmg, $lines];
}

function _use_item(array $p, array $item): array {
    $lines = [];
    switch ($item['effect']) {
        case 'heal':     $p['hp'] = min($p['max_hp'], $p['hp'] + $item['value']); $lines[] = "> [{$item['name']}] 使用。HP +{$item['value']}。"; break;
        case 'mp':       $p['mp'] = min($p['max_mp'], $p['mp'] + $item['value']); $lines[] = "> [{$item['name']}] 使用。MP +{$item['value']}。"; break;
        case 'temp_atk': $p['temp_atk'] += $item['value']; $lines[] = "> [{$item['name']}] 使用。ATK +{$item['value']}（一時的）。"; break;
        case 'temp_def': $p['temp_def'] += $item['value']; $lines[] = "> [{$item['name']}] 使用。DEF +{$item['value']}（一時的）。"; break;
        case 'escape':   $lines[] = "> [{$item['name']}] はここでは使えない（逃走コマンドで使用）。"; array_unshift($p['items'], $item); break;
        case 'gold_fever':
            $p['gold_fever_days'] = ($p['gold_fever_days'] ?? 0) + $item['value'];
            $lines[] = "> [{$item['name']}] 使用。{$item['value']}日間 獲得金×2！";
            break;
    }
    return [$p, $lines];
}

function _find_item_idx(array $p, string $effect): int|false {
    foreach ($p['items'] as $i => $item) {
        if ($item['effect'] === $effect) return $i;
    }
    return false;
}
