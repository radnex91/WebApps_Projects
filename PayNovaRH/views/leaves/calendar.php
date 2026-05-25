<?php
$pageTitle = 'Calendrier des congés';
$activeMenu = 'leaves';
$breadcrumbs = ['Congés' => '/leaves', 'Calendrier' => ''];
?>
<div class="row mb-3">
    <div class="col-md-6"><h3>Calendrier des congés approuvés</h3></div>
</div>

<div class="card">
    <div class="card-body">
        <?php
        $currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
        $currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
        $firstDay = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
        $daysInMonth = date('t', $firstDay);
        $startDay = date('N', $firstDay);
        $monthName = getMonthName($currentMonth);
        ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="<?php echo APP_URL; ?>/leaves/calendar?month=<?php echo $currentMonth == 1 ? 12 : $currentMonth - 1; ?>&year=<?php echo $currentMonth == 1 ? $currentYear - 1 : $currentYear; ?>" class="btn btn-outline-primary">
                <i class="fas fa-chevron-left"></i>
            </a>
            <h4 class="mb-0"><?php echo $monthName . ' ' . $currentYear; ?></h4>
            <a href="<?php echo APP_URL; ?>/leaves/calendar?month=<?php echo $currentMonth == 12 ? 1 : $currentMonth + 1; ?>&year=<?php echo $currentMonth == 12 ? $currentYear + 1 : $currentYear; ?>" class="btn btn-outline-primary">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>

        <div class="calendar-grid">
            <div class="text-center font-weight-bold p-1 bg-light">Lun</div>
            <div class="text-center font-weight-bold p-1 bg-light">Mar</div>
            <div class="text-center font-weight-bold p-1 bg-light">Mer</div>
            <div class="text-center font-weight-bold p-1 bg-light">Jeu</div>
            <div class="text-center font-weight-bold p-1 bg-light">Ven</div>
            <div class="text-center font-weight-bold p-1 bg-light">Sam</div>
            <div class="text-center font-weight-bold p-1 bg-light">Dim</div>

            <?php for ($i = 1; $i < $startDay; $i++): ?>
            <div class="calendar-day bg-light"></div>
            <?php endfor; ?>

            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
            <?php
            $dateStr = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
            $isToday = $dateStr === date('Y-m-d');
            $dayLeaves = array_filter($leaves, function($l) use ($dateStr) {
                return $dateStr >= $l['start_date'] && $dateStr <= $l['end_date'];
            });
            ?>
            <div class="calendar-day <?php echo $isToday ? 'today' : ''; ?>">
                <div class="font-weight-bold <?php echo $isToday ? 'text-primary' : ''; ?>"><?php echo $day; ?></div>
                <?php foreach (array_slice($dayLeaves, 0, 2) as $dl): ?>
                <div class="calendar-leave" title="<?php echo e($dl['first_name'] . ' ' . $dl['last_name']); ?>">
                    <?php echo e($dl['first_name']); ?>
                </div>
                <?php endforeach; ?>
                <?php if (count($dayLeaves) > 2): ?>
                <div class="text-muted" style="font-size:0.65rem">+<?php echo count($dayLeaves) - 2; ?></div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>