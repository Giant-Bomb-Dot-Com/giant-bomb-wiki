<?php
$tigGames = [];
for ($i = 1; $i <= 6; $i++) {
    $tigGames[] = [
        'title' => "Tekken 2",
        'date' => "🇯🇵 August 3, 1995",
        'desc' => "The sequel to Namco's original 3D fighting game adds new characters and a variety of new moves and unique attacks.",
        'img' => "https://www.giantbomb.com/a/uploads/square_small/16/164924/3375794-6525538345-sIUqS.png",
        'platforms' => ["ARC", "PS1", "+5 more"],
    ];
}
?>

<div id="landing-page-container">
    <section id="tig-container">
        <h1>Today in gaming history</h1>
        <div id="tig-games-container">
            <?php foreach ($tigGames as $index => $gameData): ?>
                <?php include __DIR__ . '/../partials/game-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
    <hr class="highlight" />
</div>
