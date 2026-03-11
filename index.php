<?php
session_start();

/* =========================
   初期キャラ生成
========================= */

function generate_character() {

    return [
        "HP"    => rand(100,250),
        "MP"    => rand(10,120),
        "ATK"   => rand(16,32),
        "DEF"   => rand(16,32),
        "AGI"   => rand(16,32),
        "LUK"   => rand(16,32),
        "MONEY" => rand(100,2500),
        "STAGE" => 1
    ];
}

/* =========================
   リセマラ
========================= */

if (isset($_GET["reroll"])) {
    $_SESSION["char"] = generate_character();
}

/* =========================
   初回生成
========================= */

if (!isset($_SESSION["char"])) {
    $_SESSION["char"] = generate_character();
}

$char = $_SESSION["char"];

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Street RPG</title>
<style>

body{
    font-family: monospace;
}

.box{
    border:1px solid #000;
    padding:10px;
    margin:10px;
    width:300px;
}

button{
    width:200px;
    margin:3px;
}

</style>
</head>

<body>

<h1>Street RPG</h1>

<div class="box">

<h3>キャラクター</h3>

HP : <?php echo $char["HP"]; ?><br>
MP : <?php echo $char["MP"]; ?><br>
ATK : <?php echo $char["ATK"]; ?><br>
DEF : <?php echo $char["DEF"]; ?><br>
AGI : <?php echo $char["AGI"]; ?><br>
LUK : <?php echo $char["LUK"]; ?><br>
MONEY : <?php echo $char["MONEY"]; ?><br>
STAGE : <?php echo $char["STAGE"]; ?><br>

<br>

<form method="get">
<button name="reroll" value="1">キャラ作り直し</button>
</form>

</div>


<div class="box">

<h3>メインマップ</h3>

<form action="fight.php">
<button>ストリートファイト</button>
</form>

<form action="inn.php">
<button>宿</button>
</form>

<form action="training.php">
<button>修練所</button>
</form>

<form action="shop_weapon.php">
<button>武器屋</button>
</form>

<form action="shop_item.php">
<button>道具屋</button>
</form>

<form action="boss.php">
<button>エリアボス</button>
</form>

</div>

</body>
</html>
