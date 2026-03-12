// ============================================================
//  effects.js — ターミナルUI / API通信 / ゲームフロー制御
// ============================================================

const API = 'api/action.php';

let G = {
    screen: 'title',
    player: null,
    mob: null,
    pendingStats: null,
    rerolls: 0,
    innCost: 0,
    weaponStock: [],
    itemStock: [],
};

// ============================================================
//  ログ出力
// ============================================================
const logArea = () => document.getElementById('log-area');

let typeQueue = [];
let typing = false;

function print(text, cls = 'prompt', delay = 0) {
    typeQueue.push({ text, cls, delay });
    if (!typing) drainQueue();
}

function printBlank() { print('', 'blank'); }

async function drainQueue() {
    typing = true;
    while (typeQueue.length > 0) {
        const { text, cls, delay } = typeQueue.shift();
        await printLine(text, cls, delay);
    }
    typing = false;
    scrollBottom();
}

function printLine(text, cls, delay) {
    return new Promise(resolve => {
        setTimeout(() => {
            const el = document.createElement('span');
            el.className = 'line ' + cls;
            el.textContent = text;
            logArea().appendChild(el);
            scrollBottom();
            resolve();
        }, delay);
    });
}

function printLines(lines, cls = 'prompt', baseDelay = 0, step = 40) {
    lines.forEach((l, i) => print(l, cls, baseDelay + i * step));
}

function scrollBottom() {
    const la = logArea();
    la.scrollTop = la.scrollHeight;
}

function clearLog() {
    logArea().innerHTML = '';
    typeQueue = [];
    typing = false;
}

// ============================================================
//  コマンドエリア
// ============================================================
function setCommands(title, buttons) {
    document.getElementById('cmd-title').textContent = title;
    const area = document.getElementById('cmd-buttons');
    area.innerHTML = '';
    buttons.forEach(b => {
        const btn = document.createElement('button');
        btn.className = 'cmd-btn' + (b.cls ? ' ' + b.cls : '');
        btn.textContent = b.label;
        if (b.disabled) btn.disabled = true;
        btn.onclick = b.action;
        area.appendChild(btn);
    });
}

function disableCommands() {
    document.querySelectorAll('.cmd-btn').forEach(b => b.disabled = true);
}

// ============================================================
//  ステータスバー
// ============================================================
function updateHUD(p) {
    if (!p) return;
    G.player = p;
    const hpPct = Math.round(p.hp / p.max_hp * 100);
    const hpCls = hpPct <= 25 ? 'danger' : hpPct <= 50 ? 'warn' : '';
    const fevDays = p.gold_fever_days || 0;
    const fevCls = fevDays > 0 ? 'active' : '';
    document.getElementById('status-bar').innerHTML = `
      <div class="status-row">
        <span class="stat-item ${hpCls}">HP:<span>${p.hp}/${p.max_hp}</span></span>
        <span class="stat-item">MP:<span>${p.mp}/${p.max_mp}</span></span>
        <span class="stat-item">¥<span>${p.money.toLocaleString()}</span></span>
        <span class="stat-item">DAY:<span>${p.day || 1}</span></span>
        <span class="stat-item ${fevCls}">護符:<span>${fevDays > 0 ? '残' + fevDays + '日' : 'OFF'}</span></span>
      </div>
      <div class="status-row">
        <span class="stat-item">ATK:<span>${p.atk}</span></span>
        <span class="stat-item">DEF:<span>${p.def}</span></span>
        <span class="stat-item">AGI:<span>${p.agi}</span></span>
        <span class="stat-item">LUK:<span>${p.luk}</span></span>
        <span class="stat-item">STG:<span>${p.stage}</span></span>
        <span class="stat-item">武器:<span>${p.weapons.length}</span></span>
        <span class="stat-item">道具:<span>${p.items.length}</span></span>
      </div>
    `;
}

// ============================================================
//  API 呼び出し
// ============================================================
async function api(action, extra = {}) {
    try {
        const res = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...extra }),
        });
        return await res.json();
    } catch (e) {
        print('> [ERROR] 通信失敗: ' + e.message, 'bad');
        return null;
    }
}

// ============================================================
//  タイトル
// ============================================================
function showTitle() {
    G.screen = 'title';
    clearLog();
    document.getElementById('status-bar').innerHTML = '';
    const lines = [
        '╔══════════════════════════════════════╗',
        '║                                      ║',
        '║    S T R E E T   D O G S             ║',
        '║                                      ║',
        '║    路地裏サバイバル RPG               ║',
        '║                                      ║',
        '╚══════════════════════════════════════╝',
        '',
        '> システム起動完了。',
        '> バージョン 0.2.0',
        '',
    ];
    lines.forEach((l, i) => print(l, i < 7 ? 'header' : 'info', i * 50));
    setTimeout(() => {
        setCommands('コマンドを入力してください', [
            { label: '[ NEW GAME ]', action: startChargen },
        ]);
    }, lines.length * 50 + 200);
}

// ============================================================
//  キャラ生成
// ============================================================
async function startChargen() {
    G.screen = 'chargen';
    G.rerolls = 0;
    clearLog();
    print('> キャラクター生成を開始する。', 'cyan');
    print('> ダイスを振れ。', 'info', 60);
    printBlank();
    await doRoll();
}

async function doRoll() {
    disableCommands();
    clearLog();
    const data = await api('roll');
    if (!data) return;
    G.pendingStats = data.stats;
    G.rerolls++;

    const s = data.stats;
    const entries = [
        ['hp', s.hp, 100, 250],
        ['mp', s.mp, 10, 120],
        ['atk', s.atk, 16, 32],
        ['def', s.def, 16, 32],
        ['agi', s.agi, 16, 32],
        ['luk', s.luk, 16, 32],
        ['money', s.money, 100, 2500],
    ];

    print(`> ──── ROLL #${G.rerolls} ────`, 'dim');
    entries.forEach(([key, val, min, max], i) => {
        const filled = Math.round((val - min) / (max - min) * 20);
        const bar = '█'.repeat(filled) + '░'.repeat(20 - filled);
        const label = key.toUpperCase().padEnd(6);
        const dispVal = key === 'money' ? ('¥' + val.toLocaleString()).padStart(8) : String(val).padStart(4);
        print(`> ${label} ${dispVal}  [${bar}]`, 'prompt', i * 40);
    });

    const ratingColor = { S: 'bad', A: 'warn', B: 'good', C: 'prompt', D: 'dim' };
    print(`> `, 'blank', entries.length * 40 + 20);
    print(`> SCORE: ${data.score}  RATING: [ ${data.rating} ]`, ratingColor[data.rating] || 'prompt', entries.length * 40 + 60);
    print(`> ${data.comment}`, 'info', entries.length * 40 + 100);
    printBlank();

    setTimeout(() => {
        setCommands('このキャラで始めるか？', [
            { label: '[ CONFIRM ]', action: confirmChar, cls: 'amber' },
            { label: '[ REROLL ]', action: doRoll },
        ]);
    }, entries.length * 40 + 300);
}

async function confirmChar() {
    disableCommands();
    const data = await api('confirm', { stats: G.pendingStats });
    if (!data?.ok) return;
    updateHUD(data.player);
    print('> キャラクター確定。', 'good');
    print('> 路地裏へ足を踏み入れた。', 'info', 80);
    printBlank();
    setTimeout(() => showMap(), 600);
}

// ============================================================
//  マップ
// ============================================================
async function showMap() {
    G.screen = 'map';
    const data = await api('map');
    if (!data) return;
    updateHUD(data.player);
    const st = data.stage_info;

    clearLog();
    print(`> ═══ STAGE ${data.player.stage}: ${st.name} ═══`, 'header');
    printBlank();

    data.nodes.forEach((node, i) => {
        print(`> ${node.tag.padEnd(10)} ${node.name}`, node.danger ? 'bad' : 'prompt', 60 + i * 40);
        print(`>            ${node.desc}`, 'info', 80 + i * 40);
        if (node.cost) print(`>            ${node.cost}`, 'warn', 90 + i * 40);
    });

    printBlank();
    if (data.player.weapons.length > 0)
        print(`> 武装: ${data.player.weapons.map(w => w.name).join(' / ')}`, 'info');
    if (data.player.items.length > 0)
        print(`> 道具: ${data.player.items.map(it => it.name).join(' / ')}`, 'info');
    printBlank();

    setTimeout(() => {
        setCommands('どこへ向かう？', [
            { label: '[FIGHT]', action: startFight },
            { label: '[REST]', action: startInn },
            { label: '[TRAIN]', action: startDojo },
            { label: '[WEAPON]', action: startWeaponShop },
            { label: '[ITEM]', action: startItemShop },
            { label: '[BOSS]', action: startBoss, cls: 'danger' },
            { label: '[STATUS]', action: showStatus },
        ]);
    }, 60 + data.nodes.length * 40 + 400);
}

function showStatus() {
    const p = G.player;
    if (!p) return;
    printBlank();
    print('> ──── STATUS ────', 'cyan');
    print(`> HP  ${p.hp}/${p.max_hp}  MP  ${p.mp}/${p.max_mp}`, 'prompt');
    print(`> ATK ${p.atk}  DEF ${p.def}  AGI ${p.agi}  LUK ${p.luk}`, 'prompt');
    print(`> ¥${p.money.toLocaleString()}  STAGE ${p.stage}`, 'prompt');
    if (p.weapons.length) print(`> 武器: ${p.weapons.map(w => w.name).join(', ')}`, 'info');
    if (p.items.length) print(`> 道具: ${p.items.map(i => i.name).join(', ')}`, 'info');
    printBlank();
}

// ============================================================
//  ストリートファイト
// ============================================================
async function startFight() {
    G.screen = 'fight';
    clearLog();
    print('> 路地裏を歩いていると……', 'info');
    const data = await api('fight_start');
    if (!data) return;
    updateHUD(data.player);
    G.mob = data.mob;
    print(`> 【${data.mob.name}】が現れた！`, 'warn', 80);
    print(`> HP: ${data.mob.hp}  ATK: ${data.mob.atk}  DEF: ${data.mob.def}`, 'info', 160);
    printBlank();
    setTimeout(() => showFightCommands(), 400);
}

function showFightCommands() {
    const p = G.player;
    const hasThrow = p.weapons.some(w => ['knife', 'bullet'].includes(w.id));
    const hasMp = p.mp >= 10;
    const hasItem = p.items.length > 0;
    setCommands(`vs 【${G.mob.name}】 HP:${G.mob.hp}/${G.mob.max_hp}`, [
        { label: '[ATTACK]', action: () => doFightAction('attack') },
        { label: '[THROW]', action: () => doFightAction('throw'), disabled: !hasThrow },
        { label: '[SKILL]', action: () => doFightAction('skill'), disabled: !hasMp },
        { label: '[DEFEND]', action: () => doFightAction('defend') },
        {
            label: hasItem ? `[ITEM: ${p.items[0].name}]` : '[ITEM]',
            action: () => doFightAction('item'), disabled: !hasItem
        },
        { label: '[RUN]', action: () => doFightAction('run') },
    ]);
}

async function doFightAction(cmd) {
    disableCommands();
    const data = await api('fight_action', { cmd });
    if (!data) return;
    updateHUD(data.player);
    G.player = data.player;
    if (data.mob) G.mob = data.mob;

    data.lines.forEach((l, i) => {
        const cls = l.includes('ダメージ') ? 'warn'
            : l.includes('倒した') ? 'good'
                : l.includes('力尽き') ? 'bad'
                    : l.includes('回避') ? 'cyan'
                        : 'prompt';
        print(l, cls, i * 60);
    });

    const delay = data.lines.length * 60 + 200;
    setTimeout(() => {
        switch (data.result) {
            case 'win':
                printBlank();
                print('> 勝利。マップに戻る。', 'good');
                setTimeout(() => showMap(), 800);
                break;
            case 'lose': gameOver(); break;
            case 'escape':
                printBlank();
                print('> 逃走成功。', 'warn');
                setTimeout(() => showMap(), 600);
                break;
            case 'boss_win':
                printBlank();
                setTimeout(() => showMap(), 1200);
                break;
            case 'game_clear': setTimeout(() => gameClear(), 1000); break;
            default: setTimeout(() => showFightCommands(), 100); break;
        }
    }, delay);
}

// ============================================================
//  ボス戦
// ============================================================
async function startBoss() {
    G.screen = 'fight';
    clearLog();
    const data = await api('boss_start');
    if (!data) return;
    updateHUD(data.player);
    G.mob = data.mob;
    print('> ───────────────────────────', 'dim');
    print(`> BOSS ENCOUNTER`, 'bad');
    print(`> 【${data.mob.name}】`, 'bad');
    print(`> HP: ${data.mob.hp}  ATK: ${data.mob.atk}  DEF: ${data.mob.def}`, 'info');
    print('> ───────────────────────────', 'dim');
    printBlank();
    setTimeout(() => showFightCommands(), 600);
}

// ============================================================
//  宿
// ============================================================
async function startInn() {
    G.screen = 'inn';
    clearLog();
    print('> 宿の前に立った。', 'info');
    const data = await api('inn_quote');
    if (!data) return;
    G.innCost = data.cost;
    updateHUD(data.player);
    printBlank();
    print(`> 今夜の宿代: ¥${data.cost.toLocaleString()}`, 'warn');
    print(`> 泊まれば HP・MP が全回復する。`, 'info');
    printBlank();
    const canAfford = data.player.money >= data.cost;
    setCommands('どうする？', [
        { label: `[泊まる ¥${data.cost.toLocaleString()}]`, action: doInnStay, disabled: !canAfford, cls: 'amber' },
        { label: '[戻る]', action: showMap },
    ]);
}

async function doInnStay() {
    disableCommands();
    const data = await api('inn_stay');
    if (!data) return;
    if (data.ok) updateHUD(data.player);
    data.lines.forEach((l, i) => print(l, data.ok ? 'good' : 'bad', i * 60));
    setTimeout(() => showMap(), data.lines.length * 60 + 500);
}

// ============================================================
//  修練所（stat_list はサーバーから受け取り動的生成）
// ============================================================
async function startDojo() {
    G.screen = 'dojo';
    clearLog();
    const data = await api('dojo_info');
    if (!data) return;
    updateHUD(data.player);
    const p = data.player;
    const cost = data.cost;
    const statList = data.stat_list; // [{key, label, note}, ...]

    print('> 修練所に入った。', 'info');
    printBlank();
    print(`> 1回 ¥${cost} で任意のステータスを鍛える。`, 'info');
    print(`> 上昇率はランダム（1〜5%）。何回でも可。`, 'info');
    printBlank();

    // 現在値を並び順通りに表示
    const statLine = statList.map(s => `${s.label}:${p[s.key]}`).join('  ');
    print(`> 現在 ─ ${statLine}`, 'prompt');
    printBlank();

    const canAfford = p.money >= cost;
    const btns = statList.map(s => ({
        label: `[${s.label}]`,
        action: () => doDojoTrain(s.key),
        disabled: !canAfford,
    }));
    btns.push({ label: '[戻る]', action: showMap });

    setTimeout(() => setCommands(`¥${cost}/回 ─ どのステータスを鍛える？`, btns), 400);
}

async function doDojoTrain(stat) {
    disableCommands();
    const data = await api('dojo_train', { stat });
    if (!data) return;
    if (data.ok) updateHUD(data.player);
    data.lines.forEach((l, i) => print(l, data.ok ? 'good' : 'bad', i * 40));
    setTimeout(() => startDojo(), data.lines.length * 40 + 400);
}

// ============================================================
//  武器屋
// ============================================================
async function startWeaponShop() {
    G.screen = 'weapon';
    clearLog();
    const data = await api('weapon_stock');
    if (!data) return;
    updateHUD(data.player);
    G.weaponStock = data.stock;

    print('> 武器屋に入った。', 'info');
    print('> 武器は重ね持ち可。ボス撃破後は没収される。', 'info');
    printBlank();

    data.stock.forEach((w, i) => {
        const dmgStr = w.dmg[1] > 0 ? `DMG:${w.dmg[0]}-${w.dmg[1]}` : '投擲専用';
        const throwStr = w.throw_dmg ? ` 投:${w.throw_dmg[0]}-${w.throw_dmg[1]}` : '';
        print(`> [${i + 1}] ${w.name.padEnd(8)} ¥${String(w.price).padStart(5)}  ${dmgStr}${throwStr}`, 'prompt', i * 50);
        print(`>     ${w.desc}`, 'info', i * 50 + 20);
    });

    printBlank();
    print(`> 現在の武装: ${data.player.weapons.length > 0 ? data.player.weapons.map(w => w.name).join(' / ') : 'なし'}`, 'dim');
    printBlank();

    const p = data.player;
    const btns = data.stock.map((w, i) => ({
        label: `[買う${i + 1}: ¥${w.price}]`,
        action: () => doBuyWeapon(i),
        disabled: p.money < w.price,
        cls: 'amber',
    }));
    btns.push({ label: '[戻る]', action: showMap });
    setTimeout(() => setCommands('どれを買う？', btns), data.stock.length * 50 + 300);
}

async function doBuyWeapon(idx) {
    disableCommands();
    const data = await api('weapon_buy', { idx });
    if (!data) return;
    if (data.ok) updateHUD(data.player);
    data.lines.forEach((l, i) => print(l, data.ok ? 'good' : 'bad', i * 40));
    setTimeout(() => startWeaponShop(), data.lines.length * 40 + 400);
}

// ============================================================
//  道具屋
// ============================================================
async function startItemShop() {
    G.screen = 'item';
    clearLog();
    const data = await api('item_stock');
    if (!data) return;
    updateHUD(data.player);

    print('> 道具屋に入った。', 'info');
    print('> アイテムはボス撃破後に没収される。', 'info');
    printBlank();

    data.stock.forEach((it, i) => {
        print(`> [${i + 1}] ${it.name.padEnd(10)} ¥${String(it.price).padStart(5)}  ${it.desc}`, 'prompt', i * 50);
    });

    printBlank();
    print(`> 所持道具: ${data.player.items.length > 0 ? data.player.items.map(i => i.name).join(' / ') : 'なし'}`, 'dim');
    printBlank();

    const p = data.player;
    const btns = data.stock.map((it, i) => ({
        label: `[買う${i + 1}: ¥${it.price}]`,
        action: () => doBuyItem(i),
        disabled: p.money < it.price,
        cls: 'amber',
    }));
    btns.push({ label: '[戻る]', action: showMap });
    setTimeout(() => setCommands('どれを買う？', btns), data.stock.length * 50 + 300);
}

async function doBuyItem(idx) {
    disableCommands();
    const data = await api('item_buy', { idx });
    if (!data) return;
    if (data.ok) updateHUD(data.player);
    data.lines.forEach((l, i) => print(l, data.ok ? 'good' : 'bad', i * 40));
    setTimeout(() => startItemShop(), data.lines.length * 40 + 400);
}

// ============================================================
//  ゲームオーバー / クリア
// ============================================================
function gameOver() {
    clearLog();
    document.getElementById('crt-wrap').classList.add('flash');
    setTimeout(() => document.getElementById('crt-wrap').classList.remove('flash'), 300);
    const lines = [
        '> ───────────────────────────',
        '> DEAD',
        '> ',
        '> お前は路地裏に倒れた。',
        '> 名前も残らない。',
        '> ───────────────────────────',
    ];
    lines.forEach((l, i) => print(l, 'bad', i * 80));
    setTimeout(() => {
        setCommands('', [{ label: '[RETRY]', action: async () => { await api('reset'); showTitle(); } }]);
    }, lines.length * 80 + 300);
}

function gameClear() {
    clearLog();
    const p = G.player;
    const lines = [
        '> ═══════════════════════════════════',
        '> GAME CLEAR',
        '> ',
        '> 全ステージ制覇。',
        '> お前が路地裏の王だ。',
        '> ',
        `> 最終HP: ${p.hp}/${p.max_hp}`,
        `> 所持金: ¥${p.money.toLocaleString()}`,
        '> ═══════════════════════════════════',
    ];
    lines.forEach((l, i) => print(l, 'good', i * 100));
    setTimeout(() => {
        setCommands('', [{ label: '[もう一度]', action: async () => { await api('reset'); showTitle(); } }]);
    }, lines.length * 100 + 300);
}

// ============================================================
//  起動
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    showTitle();
});
