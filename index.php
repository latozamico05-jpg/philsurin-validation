<?php
require 'config.php';
// Encoding is local-only: no Google Sheet / Apps Script sync UI or background mirror.
// save_validation handling lives below the login gate; location_id is owned by
// save_validation_rows() (project | date_planted | dr_dp | control_name).
//
// Logout: fully clear the session AND expire the cookie, then bounce to the
// login screen. config.php suppresses page assets on ?logout=1 so the
// Location header below is never blocked by "headers already sent".
if(isset($_GET['logout'])){
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location:index.php');
    exit;
}
$error='';$message='';
if(isset($_POST['login'])){
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $q=$db->prepare('SELECT * FROM users WHERE username=? AND active=1');
    $q->execute([$username]);
    $u=$q->fetch();
    $passwordValid=$u&&(password_verify($password,$u['password_hash'])||hash_equals(hash('sha256',$password),(string)$u['password_hash']));
    if($passwordValid){
        // New session id on privilege change (blocks session fixation).
        session_regenerate_id(true);
        $_SESSION['user']=['id'=>$u['id'],'username'=>$u['username'],'role'=>$u['role']??'viewer'];
        audit_log((int)$u['id'],'LOGIN','USER',(string)$u['id']);
        // AUTO-CONNECT: honor ?next= (e.g. audit-auto.php -> /audit.php) so the
        // tunnel lands straight on audit.php after login — no second click.
        $next = trim((string)($_GET['next'] ?? $_POST['next'] ?? ''));
        if ($next !== '' && preg_match('#^(https://lnmmkbpg-3000\.asse\.devtunnels\.ms|https?://localhost(:\d+)?|https?://127\.0\.0\.1(:\d+)?)(/.*)?$#i', $next)) {
            header('Location:' . $next);
            exit;
        }
        header('Location:'.(strtolower((string)($_SESSION['user']['role']??'viewer'))==='viewer'?'dashboard.php':'index.php'));
        exit;
    }
    audit_log($u ? (int)$u['id'] : null,'LOGIN_FAILED','USER',(string)$username);
    $error='Invalid username or password.';
}
if(empty($_SESSION['user'])):
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="style.css">
<title>PHILSURIN Login</title>
<script src="password-eye.js"></script>
</head>
<body>
<main class="card login">
    <h1>PHILSURIN</h1>
    <p>Sugarcane Variety Validation</p>
    <?php if($error): ?>
        <div class="login-error" role="alert" aria-live="assertive"><?=e($error)?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
        <?php if(!empty($_GET['next'])): ?><input type="hidden" name="next" value="<?=e((string)$_GET['next'])?>"><?php endif; ?>
        <input name="username" placeholder="Username" value="<?=e((string)($_POST['username']??''))?>" autocapitalize="off" autocorrect="off" spellcheck="false" required>
        <input name="password" type="password" placeholder="Password" autocomplete="current-password" required>
        <button name="login">LOG IN</button>
    </form>
    <?php if($error): ?>
        <button type="button" class="login-error-dismiss" onclick="this.parentNode.removeChild(this.previousElementSibling);this.remove()">DISMISS</button>
    <?php endif; ?>
</main>
</body>
</html>
<?php exit;endif; ?>
<?php
if(isset($_POST['save_validation'])){
    if(!in_array(strtolower((string)($_SESSION['user']['role']??'viewer')),['admin','validator','validator_germination','validator_field','validator_harvest'],true)){
        $error='Your account can view records but cannot save Validation data.';
    }elseif(!philsurin_can_encode_stage((string)($_POST['stage']??''))){
        $error='Your validator account is not allowed to encode this grading stage.';
    }else{
        try{
            $result=save_validation_rows($db,(int)$_SESSION['user']['id'],$_POST);
            audit_log((int)$_SESSION['user']['id'],'SAVE_VALIDATION','VALIDATION',(string)($result['location_id']??''),null,['stage'=>normalize_stage_name($_POST['stage']??''),'variety_id'=>(int)$_POST['variety_id'],'saved'=>(int)($result['saved']??0)]);
            $params=http_build_query(['saved'=>1,'location'=>$result['location_id'],'stage'=>normalize_stage_name($_POST['stage']??''),'variety_id'=>(int)$_POST['variety_id']]);
            header('Location:index.php?'.$params);
            exit;
        }catch(Throwable $x){
            $error=$x->getMessage();
        }
    }
}
// Form data for the encoding screen. Initialised here (guarded) so the page can
// never emit "Undefined variable $stages / $varieties" on any code path.
$stages = (isset($stages) && is_array($stages) && $stages)
    ? $stages
    : (function_exists('grading_stage_list') ? grading_stage_list($db) : ['01 GRADING','02 GRADING','03 GRADING','04 GRADING','HARVEST']);
$varieties = (isset($varieties) && is_array($varieties))
    ? $varieties
    : $db->query('SELECT * FROM varieties WHERE active=1 ORDER BY code')->fetchAll();
// Which grading the Validation Form must show. After SAVE, index.php redirects with
// ?stage=... so the reopened form keeps the grading the encoder was working on.
$activeStage = normalize_stage_name($_POST['stage'] ?? $_GET['stage'] ?? '01 GRADING');
if ($activeStage === '') {
    $activeStage = '01 GRADING';
}
// The Validation Form is rendered SERVER-SIDE from stage_fields() — the same helper
// save_validation_rows() uses to map each input onto its database column. Rendering
// these inputs only in JavaScript left all four Replicate cards completely blank
// whenever validation-form.js was blocked, stale-cached or threw early, so the form
// is now usable with JavaScript disabled and validation-form.js only enhances it.
$activeStageFields = stage_fields($activeStage);
// Stage selected after a save/redirect, so its button shows as active on first paint.
$activeStageKey = strtoupper(preg_replace('/\s+/', ' ', trim($activeStage)));
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="style.css">
<title>PHILSURIN Grading</title>
<script src="password-eye.js"></script>
</head>
<body>
<main>
    <header>
        <h1>PHILSURIN</h1>
        <span><?=e($_SESSION['user']['username'])?> | <a href="?logout=1">Log out</a></span>
    </header>
    <?php if($message):?><p class="ok"><?=e($message)?></p><?php endif ?>
    <?php if($error):?><p class="error"><?=e($error)?></p><?php endif ?>
    <form method="post">
        <section class="card">
            <h2>Trial / Grading Setup</h2>
            <div class="grid four">
                <label>Location ID<input name="location_id" value="Appears automatically" required></label>
                <label>Location of Project<input name="project_location" value="HAWAIIAN_SILAY" required></label>
                <label>Date Planted<input type="date" name="date_planted"></label>
                <label>DR / DP<input type="date" name="dr_dp"></label>
            </div>
            <label>Check / Control<input name="control_name" value="MYCK"></label>
            <div class="stages"><b>Gradings at:</b>
                <?php foreach($stages as $s):
                    $sKey = strtoupper(preg_replace('/\s+/', ' ', trim((string)$s)));?>
                    <button type="button" class="stage<?=$sKey === $activeStageKey ? ' active' : ''?>" data-stage="<?=e($s)?>"><?=e($s)?></button>
                <?php endforeach ?>
                <input type="hidden" name="stage" id="stage" value="<?=e($activeStage)?>">
            </div>
        </section>
        <section class="card">
            <div class="grid three">
                <label>Variety<select name="variety_id" required>
                    <option value="">-- Select Variety --</option>
                    <?php foreach($varieties as $v):?>
                        <option value="<?=$v['id']?>"><?=e($v['code'].' - '.$v['name'])?></option>
                    <?php endforeach ?>
                </select></label>
                <label>Size of Location<input name="location_size" placeholder="None" required></label>
                <label>Date<input type="date" name="grading_date" value="<?=date('Y-m-d')?>" required></label>
            </div>
            <h2>Validation Form</h2>
            <div class="replicates">
                <?php for($i=1;$i<=4;$i++):?>
                    <article>
                        <h3>Replicate <?=$i?> <small>REP <?=$i?> - <?=e($activeStage)?></small></h3>
                        <label>Variety<input value="Select Variety above" disabled></label>
                        <div class="stage-fields" data-rep="<?=$i?>">
                            <?php foreach($activeStageFields as $field):
                                $fieldName = $field['key'] === 'remarks' ? 'remarks' : 'field_'.$field['key'];
                                $inputName = $fieldName.'['.$i.']';
                                $isTextarea = $field['type'] === 'textarea';
                                // type="any" step keeps decimals (1.5 cm) saveable for numbers.
                                $attrs = $isTextarea ? '' : ' type="'.e($field['type']).'"'.($field['type'] === 'number' ? ' step="any"' : '');
                            ?>
                                <label><?=e($field['label'])?><?php if($isTextarea):?><textarea name="<?=e($inputName)?>"></textarea><?php else:?><input name="<?=e($inputName)?>"<?=$attrs?>><?php endif ?></label>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endfor; ?>
            </div>
            <button name="save_validation" class="save">SAVE VALIDATION</button>
        </section>
    </form>
</main>
<script src="validation-form.js"></script>
</body>
</html>

