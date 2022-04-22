
<h2>
    <i class="fas fa-trophy"></i> 
    <?= t('Records', 'Рекорды') ?>
</h2>
<table class='table_new'>
    <thead>
        <tr>
            <td><?= t('Event', 'Дисцпилина') ?></td>
            <td><?= t('Type', 'Тип') ?></td>
            <td><?= t('Name', 'Имя') ?></td>
            <td class="attempt"><?= t('Result', 'Результат') ?></td>
            <td><?= t('Competition', 'Соревнование') ?></td>
            <td colspan='5' align='center'><?= t('Solves', 'Сборки') ?></td>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($events_dict as $event) {
            $event_record = $ratings[$event->id] ?? [];
            $record_types = [
                t('Single', 'Лучшая') => current($event_record['best'] ?? []),
                t('Average', 'Среднее') => current($event_record['average'] ?? [])];
            foreach ($record_types as $type => $record) {
                if ($record) {
                    ?>
                    <tr>
                        <td>
                            <i class='<?= $event->image ?>'></i>
                            <?= $event->name ?>
                        </td>
                        <td><?= $type ?>
                        </td>
                        <td>
                            <a href="<?= PageIndex() . "competitions/rankings/competitor/$record->FCID" ?>">
                                <?= $record->competitor_name ?>
                            </a>
                        </td>
                        <td class='record'>
                            <?= $record->result ?>
                        </td>
                        <td>
                            <a href="<?= PageIndex() ?>competitions/<?= $record->competition_secret ?>">
                                <?= $record->competition_name ?>
                            </a>
                        </td>
                        <?php foreach (range(1, 5) as $i) { ?>
                            <td class='attempt'>
                                <?= $record->{"attempt$i"} ?? false ?>
                            </td>
                        <?php } ?>
                    </tr>
                    <?php
                }
            }
        }
        ?>
    </tbody>
</table>
<hr>
<h2>
    <i class="fas fa-history"></i>
    <?= t('History of records', 'История рекордов') ?>
</h2>
<table class='table_new'>
    <thead>
        <tr>
            <td><?= t('Event', 'Дисциплина') ?></td>
            <td class="attempt"><?= t('Single', 'Лучшая') ?></td>
            <td class="attempt"><?= t('Average', 'Среднее') ?></td>            
            <td><?= t('Name', 'Имя') ?></td>
            <td><?= t('Competition', 'Соревнование') ?></td>
            <td colspan='5' align='center'><?= t('Solves', 'Сборки') ?></td>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($events_dict as $event) {
            foreach (['best', 'average'] as $type_att) {
                foreach (array_reverse($history[$event->id][$type_att] ?? []) as $r => $row) {
                    ?>
                    <tr>
                        <td>
                            <i class='<?= $event->image ?>'></i>
                            <?= $event->name ?>
                        </td>
                        <td class="<?= (!$r and$type_att == 'best' ) ? 'record' : 'attempt' ?>">
                            <?= $type_att == 'best' ? $row->result : '' ?>
                        </td>
                        <td class="<?= (!$r and$type_att == 'average' ) ? 'record' : 'attempt' ?>">
                            <?= $type_att == 'average' ? $row->result : '' ?>
                        </td>
                        <td>
                            <a href="<?= PageIndex() . "competitions/rankings/competitor/$row->FCID" ?>">
                                <?= $row->competitor_name ?>
                            </a>
                        </td>
                        <td>
                            <a href="<?= PageIndex() ?>competitions/<?= $row->competition_secret ?>">
                                <?= $row->competition_name ?>
                            </a>
                        </td>
                        <?php foreach (range(1, 5) as $i) { ?>
                            <td class='attempt'>
                                <?= $row->{"attempt$i"} ?? false ?>
                            </td>
                        <?php } ?>
                    </tr>
                    <?php
                }
            }
        }
        ?>
    </tbody>
</table>