
<?= trim(explode('(', $me->name)[0]) ?>
<?php if (!$me->wca_id) { ?>
    <sup style="color:green">
        <?= $me->id ?>
    </sup>
<?php } ?>

<a href="<?= PageIndex() ?>competitor/?action=logout">
    <i class="fas fa-sign-out-alt"></i>
    Sign out
</a>
<br>
<?php if (\api\get_me()->is_admin) { ?>
    <i class="fas fa-user-tag"></i> Admin
<?php } elseif (\api\get_me()->is_federation) { ?>
    <i class="fas fa-user-tag"></i> Federation
<?php } ?>
<?php if ($comp->my ?? false) { ?>
    <i class="fas fa-user-tag"></i> Main Organizer
<?php } elseif ($comp->organizer ?? false) { ?>
    <i class="fas fa-user-tag"></i> Organizer
<?php } ?>
&nbsp;&nbsp;
<?php $lang = $_SESSION['lang'] ?? false ?>
<?php if ($lang == 'RU') { ?>
    <i class="flag-icon flag-icon-ru"></i> <b>Ру</b> | <a href="<?= PageIndex() ?>competitor/?action=language&lang=EN">En</a>
<?php } else { ?>
    <a href="<?= PageIndex() ?>competitor/?action=language&lang=RU">Ру</a> | <b>En</b> <i class="flag-icon flag-icon-gb"></i>
<?php } ?>