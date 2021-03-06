<?php
/**
 * This software is governed by the CeCILL-B license. If a copy of this license
 * is not distributed with this file, you can obtain one at
 * http://www.cecill.info/licences/Licence_CeCILL-B_V1-en.txt
 *
 * Authors of STUdS (initial project): Guilhem BORGHESI (borghesi@unistra.fr) and Raphaël DROZ
 * Authors of Framadate/OpenSondate: Framasoft (https://github.com/framasoft)
 *
 * =============================
 *
 * Ce logiciel est régi par la licence CeCILL-B. Si une copie de cette licence
 * ne se trouve pas avec ce fichier vous pouvez l'obtenir sur
 * http://www.cecill.info/licences/Licence_CeCILL-B_V1-fr.txt
 *
 * Auteurs de STUdS (projet initial) : Guilhem BORGHESI (borghesi@unistra.fr) et Raphaël DROZ
 * Auteurs de Framadate/OpenSondage : Framasoft (https://github.com/framasoft)
 */
namespace Framadate;

session_start();

if (file_exists('bandeaux_local.php')) {
    include_once('bandeaux_local.php');
} else {
    include_once('bandeaux.php');
}

include_once __DIR__ . '/app/inc/init.php';

// Le fichier studs.php sert a afficher les résultats d'un sondage à un simple utilisateur.
// C'est également l'interface pour ajouter une valeur à un sondage deja créé.
$numsondage = false;

//On récupère le numéro de sondage par le lien web.
if(Utils::issetAndNoEmpty('sondage', $_GET) === true) {
    $numsondage = $_GET["sondage"];
    $_SESSION["numsondage"] = $numsondage;
}

if(Utils::issetAndNoEmpty('sondage') === true) {
    $numsondage = $_POST["sondage"];
    $_SESSION["numsondage"] = $numsondage;
} elseif(Utils::issetAndNoEmpty('sondage', $_COOKIE) === true) {
    $numsondage = $_COOKIE["sondage"];
} elseif(Utils::issetAndNoEmpty('numsondage', $_SESSION) === true) {
    $numsondage = $_SESSION["numsondage"];
}

$dsondage = ($numsondage != false) ? Utils::get_sondage_from_id($numsondage) : false;
if (!$dsondage || $dsondage->id_sondage == ''){
    Utils::print_header( _("Error!"));

    bandeau_titre(_("Error!"));

    echo '
    <div class="alert alert-warning">
        <h2>' . _("This poll doesn't exist !") . '</h2>
        <p>' . _('Back to the homepage of ') . ' <a href="' . Utils::get_server_name() . '"> ' . NOMAPPLICATION . '</a></p>
    </div>'."\n";

    bandeau_pied();

    die();
}

//output a CSV and die()
if(Utils::issetAndNoEmpty('export', $_GET) && $dsondage !== false) {
    if($_GET['export'] == 'csv') {
        require_once('exportcsv.php');
    }

    die();
}

// quand on ajoute un commentaire utilisateur
if(isset($_POST['ajoutcomment'])) {
    if (isset($_SESSION['nom']) && Utils::issetAndNoEmpty('commentuser') === false) {
        // Si le nom vient de la session, on le de-htmlentities
        $comment_user = html_entity_decode($_SESSION['nom'], ENT_QUOTES, 'UTF-8');
    } elseif(Utils::issetAndNoEmpty('commentuser')) {
        $comment_user = $_POST["commentuser"];
    } elseif(isset($_POST["commentuser"])) {
        $err |= COMMENT_USER_EMPTY;
    } else {
        $comment_user = _('anonyme');
    }

    if(Utils::issetAndNoEmpty('comment') === false) {
        $err |= COMMENT_EMPTY;
    }

    if (isset($_POST["comment"]) && !Utils::is_error(COMMENT_EMPTY) && !Utils::is_error(NO_POLL) && !Utils::is_error(COMMENT_USER_EMPTY)) {
        // protection contre les XSS : htmlentities
        $comment = htmlentities($_POST['comment'], ENT_QUOTES, 'UTF-8');
        $comment_user = htmlentities($comment_user, ENT_QUOTES, 'UTF-8');

        // Check for doublons
        $comment_doublon = false;
        $req = 'SELECT * FROM comments WHERE id_sondage='.$connect->Param('numsondage').' ORDER BY id_comment';
        $sql = $connect->Prepare($req);
        $comment_user_doublon = $connect->Execute($sql, array($numsondage));
        if ($comment_user_doublon->RecordCount() != 0) {
            while ( $dcomment_user_doublon=$comment_user_doublon->FetchNextObject(false)) {
                if($dcomment_user_doublon->comment == $comment && $dcomment_user_doublon->usercomment == $comment_user) {
                    $comment_doublon = true;
                };
            }
        }

        if(!$comment_doublon) {
            $req = 'INSERT INTO comments (id_sondage, comment, usercomment) VALUES ('.
                $connect->Param('id_sondage').','.
                $connect->Param('comment').','.
                $connect->Param('comment_user').')';
            $sql = $connect->Prepare($req);

            $comments = $connect->Execute($sql, array($numsondage, $comment, $comment_user));
            if ($comments === false) {
                $err |= COMMENT_INSERT_FAILED;
            }
        }
    }
}


// Action quand on clique le bouton participer
$sql = 'SELECT * FROM user_studs WHERE id_sondage='.$connect->Param('numsondage').' ORDER BY id_users';
$sql = $connect->Prepare($sql);
$user_studs = $connect->Execute($sql, array($numsondage));

$nbcolonnes = substr_count($dsondage->sujet, ',') + 1;
if (!Utils::is_error(NO_POLL) && (isset($_POST["boutonp"]))) {
    //Si le nom est bien entré
    if (Utils::issetAndNoEmpty('nom') === false) {
        $err |= NAME_EMPTY;
    }

    if(!Utils::is_error(NAME_EMPTY) && (! ( USE_REMOTE_USER && isset($_SERVER['REMOTE_USER']) ) || $_POST["nom"] == $_SESSION["nom"])) {
        $nouveauchoix = '';
        for ($i=0;$i<$nbcolonnes;$i++) {
            // radio checked 1 = Yes, 2 = Ifneedbe, 0 = No
            if (isset($_POST["choix$i"])) {
                switch ($_POST["choix$i"]) {
                    case 1: $nouveauchoix .= "1";break;
                    case 2: $nouveauchoix .= "2";break;
                    default: $nouveauchoix .= "0";break;
                }
            }
        }

        $nom=substr($_POST["nom"],0,64);

        // protection contre les XSS : htmlentities
        $nom = htmlentities($nom, ENT_QUOTES, 'UTF-8');

        while($user = $user_studs->FetchNextObject(false)) {
            if ($nom == $user->nom) {
                $err |= NAME_TAKEN;
            }
        }

        // Ecriture des choix de l'utilisateur dans la base
        if (!Utils::is_error(NAME_TAKEN) && !Utils::is_error(NAME_EMPTY)) {

           $sql = 'INSERT INTO user_studs (nom,id_sondage,reponses) VALUES ('.
               $connect->Param('nom').', '.
               $connect->Param('numsondage').', '.
               $connect->Param('nouveauchoix').')';
           $sql = $connect->Prepare($sql);

           // Todo : Il faudrait lever une erreur en cas d'erreur d'insertion
           $connect->Execute($sql, array($nom, $numsondage, $nouveauchoix));

            if ($dsondage->mailsonde || /* compatibility for non boolean DB */ $dsondage->mailsonde=="yes" || $dsondage->mailsonde=="true") {
                if($config['use_smtp']==true){
                    Utils::sendEmail( "$dsondage->mail_admin",
                       "[".NOMAPPLICATION."] "._("Poll's participation")." : ".html_entity_decode($dsondage->titre, ENT_QUOTES, 'UTF-8')."",
                       html_entity_decode("\"$nom\" ", ENT_QUOTES, 'UTF-8').
                       _("has filled a line.\nYou can find your poll at the link") . " :\n\n".
                       Utils::getUrlSondage($numsondage) . " \n\n" .
                       _("Thanks for your trust.") . "\n". NOMAPPLICATION );
                }
            }
        }
    } else {
        $err |= NAME_EMPTY;
    }
}

if($err != 0) {
    Utils::print_header(_("Error!").' - '.$dsondage->titre);
    bandeau_titre(_("Error!"));

    echo '<div class="alert alert-danger"><ul class="list-unstyled">'."\n";

    if(Utils::is_error(NAME_EMPTY)) {
        echo '<li>' . _("Enter a name") . "</li>\n";
    }
    if(Utils::is_error(NAME_TAKEN)) {
        echo '<li>' . _("The name you've chosen already exist in this poll!") . "</li>\n";
    }
    if(Utils::is_error(COMMENT_EMPTY) || Utils::is_error(COMMENT_USER_EMPTY)) {
        echo '<li>' . _("Enter a name and a comment!") . "</li>\n";
    }
    if(Utils::is_error(COMMENT_INSERT_FAILED) ) {
        echo '<li>' . _("Failed to insert the comment!") . "</li>\n";
    }

    echo '</ul></div>';

} else {
    Utils::print_header(_('Poll').' - '.$dsondage->titre);
    bandeau_titre(_('Poll').' - '.$dsondage->titre);
}

$title=stripslashes(str_replace("\\","",$dsondage->titre));
echo '
        <div class="jumbotron">
            <div class="row">
                <div class="col-md-7">
                    <h3>'.$title.'</h3>
                </div>
                <div class="col-md-5">
                    <div class="btn-group pull-right">
                        <button onclick="javascript:print(); return false;" class="btn btn-default"><span class="glyphicon glyphicon-print"></span> ' . _('Print') . '</button>
                        <button onclick="window.location.href=\'' . Utils::get_server_name() . 'exportcsv.php?numsondage=' . $numsondage . '\';return false;" class="btn btn-default"><span class="glyphicon glyphicon-download-alt"></span> ' . _('Export to CSV') . '</button>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-5">
                    <div class="form-group">
                        <h4 class="control-label">'. _("Initiator of the poll") .'</h4>
                        <p class="form-control-static"> '.stripslashes($dsondage->nom_admin).'</p>
                    </div>
                    <div class="form-group">
                        <label for="public-link"><a class="public-link" href="' . Utils::getUrlSondage($dsondage->id_sondage) . '">'._("Public link of the poll") .' <span class="btn-link glyphicon glyphicon-link"></span></a></label>
                        <input class="form-control" id="public-link" type="text" readonly="readonly" value="' . Utils::getUrlSondage($dsondage->id_sondage) . '" />
                    </div>
                </div>'."\n";

//affichage de la description du sondage
if ($dsondage->commentaires) {
    $commentaires = $dsondage->commentaires;
    $commentaires=nl2br(str_replace("\\","",$commentaires));
    echo '
                <div class="form-group col-md-7">
                    <h4 class="control-label">'._("Description") .'</h4><br />
                    <p class="form-control-static well">'. $commentaires .'</p>
                </div>';
}
echo '
            </div>
        </div>'."\n"; // .jumbotron

//On récupere les données et les sujets du sondage
$nblignes = $user_studs->RecordCount();

//on teste pour voir si une ligne doit etre modifiée
$testmodifier = false;
$ligneamodifier = -1;
for ($i=0;$i<$nblignes;$i++) {
    if (isset($_POST["modifierligne$i"])) {
        $ligneamodifier = $i;
    }

    //test pour voir si une ligne est a modifier
    if (isset($_POST['validermodifier'.$i])) {
        $modifier = $i;
        $testmodifier = true;
    }
}

//si le test est valide alors on affiche des checkbox pour entrer de nouvelles valeurs
if ($testmodifier) {
    $nouveauchoix = '';
    for ($i=0;$i<$nbcolonnes;$i++) {
        // radio checked 1 = Yes, 2 = Ifneedbe, 0 = No
        if (isset($_POST["choix$i"])) {
            switch ($_POST["choix$i"]) {
                case 1: $nouveauchoix .= "1";break;
                case 2: $nouveauchoix .= "2";break;
                default: $nouveauchoix .= "0";break;
            }
        }
    }

    $compteur=0;
    while ($data = $user_studs->FetchNextObject(false) ) {
        //mise a jour des données de l'utilisateur dans la base SQL
        if ($compteur == $modifier) {
            $sql = 'UPDATE user_studs SET reponses='.$connect->Param('nouveauchoix').' WHERE nom='.$connect->Param('nom').' AND id_users='.$connect->Param('id_users');
            $sql = $connect->Prepare($sql);
            $connect->Execute($sql, array($nouveauchoix, $data->nom, $data->id_users));

            if ($dsondage->mailsonde=="yes") {
                Utils::sendEmail( "$dsondage->mail_admin", "[".NOMAPPLICATION."] " . _("Poll's participation") . " : ".html_entity_decode($dsondage->titre, ENT_QUOTES, 'UTF-8'), "\"".html_entity_decode($data->nom, ENT_QUOTES, 'UTF-8')."\""."" . _("has filled a line.\nYou can find your poll at the link") . " :\n\n" . Utils::getUrlSondage($numsondage) . " \n\n" . _("Thanks for your trust.") . "\n".NOMAPPLICATION );
            }
        }
        $compteur++;
    }
}

//recuperation des utilisateurs du sondage
$sql = 'SELECT * FROM user_studs WHERE id_sondage='.$connect->Param('numsondage').' ORDER BY id_users';
$sql = $connect->Prepare($sql);
$user_studs = $connect->Execute($sql, array($numsondage));

//reformatage des données des sujets du sondage
$toutsujet = explode(",",$dsondage->sujet);

// Table headers
$thead = '<thead>';

// Button in the first td to avoid remove col on "Return" keypress)
$border = array(); // bordure pour distinguer les mois
$td_headers = array(); // for a11y, headers="M1 D4 H5" on each td
$radio_title = array(); // date for

// Dates poll
if ($dsondage->format=="D"||$dsondage->format=="D+"||$dsondage->format=="D-") {

    $tr_months = '<tr><th role="presentation"></th>';
    $tr_days = '<tr><th role="presentation"></th>';
    $tr_hours = '<tr><th role="presentation"></th>';

    // Headers
    $colspan_month = 1;
    $colspan_day = 1;

    for ($i = 0; $i < count($toutsujet); $i++) {

        // Current date
        $current = $toutsujet[$i];
        $horoCur = explode("@",$current); //horoCur[0] = date, horoCur[1] = hour
        if (isset($toutsujet[$i+1])){
            $next = $toutsujet[$i+1];
            $horoNext = explode("@",$next);
        }

        $border[$i] = false;
        $radio_title[$i] = strftime($date_format['txt_short'],$horoCur[0]);

        // Months
        $td_headers[$i] = 'M'.($i+1-$colspan_month);

        if (isset($toutsujet[$i+1]) && strftime("%B", $horoCur[0]) == strftime("%B", $horoNext[0]) && strftime("%Y", $horoCur[0]) == strftime("%Y", $horoNext[0])){
            $colspan_month++;
        } else {
            $border[$i] = true;
            $tr_months .= '<th colspan="'.$colspan_month.'" class="bg-primary month" id="M'.($i+1-$colspan_month).'">'.strftime("%B",$horoCur[0]).' '.strftime("%Y", $horoCur[0]).'</th>';
            $colspan_month=1;
        }

        // Days
        $td_headers[$i] .= ' D'.($i+1-$colspan_day);

        if (isset($toutsujet[$i+1]) && strftime($date_format['txt_day'],$horoCur[0])==strftime($date_format['txt_day'],$horoNext[0])&&strftime("%B",$horoCur[0])==strftime("%B",$horoNext[0])){
            $colspan_day++;
        } else {
            $rbd = ($border[$i]) ? ' rbd' : '';
            $tr_days .= '<th colspan="'.$colspan_day.'" class="bg-primary day'.$rbd.'" id="D'.($i+1-$colspan_day).'">'.strftime($date_format['txt_day'],$horoCur[0]).'</th>';
            $colspan_day=1;
        }

        // Hours
        $rbd = ($border[$i]) ? ' rbd' : '';
        if (isset($horoCur[1]) && $horoCur[1] !== '') {
                $tr_hours .= '<th class="bg-info'.$rbd.'" id="H'.$i.'" title="'.$horoCur[1].'">'.$horoCur[1].'</th>';
                $radio_title[$i] .= ' - '.$horoCur[1];
                $td_headers[$i] .= ' H'.$i;
        } else {
                $tr_hours .= '<th class="bg-info'.$rbd.'"></th>';
        }
    }

    $border[count($border)-1] = false; // suppression de la bordure droite du dernier mois

    $tr_months .= '<th></th></tr>';
    $tr_days .= '<th></th></tr>';
    $tr_hours .= '<th></th></tr>';

    $thead = "\n".$tr_months."\n".$tr_days."\n".$tr_hours."\n";

// Subjects poll
} else {
    $toutsujet=str_replace("@","<br />",$toutsujet);

    $tr_subjects = '<tr><th role="presentation"></th>';

    for ($i = 0; isset($toutsujet[$i]); $i++) {

        $td_headers[$i]='';$radio_title[$i]=''; // init before concatenate

        // Subjects
        preg_match_all('/\[!\[(.*?)\]\((.*?)\)\]\((.*?)\)/',$toutsujet[$i],$md_a_img);  // Markdown [![alt](src)](href)
        preg_match_all('/!\[(.*?)\]\((.*?)\)/',$toutsujet[$i],$md_img);                 // Markdown ![alt](src)
        preg_match_all('/\[(.*?)\]\((.*?)\)/',$toutsujet[$i],$md_a);                    // Markdown [text](href)
        if (isset($md_a_img[2][0]) && $md_a_img[2][0]!='' && isset($md_a_img[3][0]) && $md_a_img[3][0]!='') { // [![alt](src)](href)

            $th_subject_text = (isset($md_a_img[1][0]) && $md_a_img[1][0]!='') ? stripslashes($md_a_img[1][0]) : _("Choice") .' '.($i+1);
            $th_subject_html = '<a href="'.$md_a_img[3][0].'"><img src="'.$md_a_img[2][0].'" class="img-responsive" alt="'.$th_subject_text.'" /></a>';

        } elseif (isset($md_img[2][0]) && $md_img[2][0]!='') { // ![alt](src)

            $th_subject_text = (isset($md_img[1][0]) && $md_img[1][0]!='') ? stripslashes($md_img[1][0]) : _("Choice") .' '.($i+1);
            $th_subject_html = '<img src="'.$md_img[2][0].'" class="img-responsive" alt="'.$th_subject_text.'" />';

        } elseif (isset($md_a[2][0]) && $md_a[2][0]!='') { // [text](href)

            $th_subject_text = (isset($md_a[1][0]) && $md_a[1][0]!='') ? stripslashes($md_a[1][0]) : _("Choice") .' '.($i+1);
            $th_subject_html = '<a href="'.$md_a[2][0].'">'.$th_subject_text.'</a>';

        } else { // text only

            $th_subject_text = stripslashes($toutsujet[$i]);
            $th_subject_html = $th_subject_text;

        }
        $tr_subjects .= '<th class="bg-info" id="S'.$i.'" title="'.$th_subject_text.'">'.$th_subject_html.'</th>';

        $border[$i] = false;
        $td_headers[$i] .= 'S'.$i;
        $radio_title[$i] .= $th_subject_text;

   }

    $thead = $tr_subjects.'<th></th></tr>';
}

// Print headers
echo '
<form name="formulaire" action="' . Utils::getUrlSondage($dsondage->id_sondage) . '" method="POST">
    <input type="hidden" name="sondage" value="' . $numsondage . '"/>
';
if ($dsondage->format=="A-" || $dsondage->format=="D-") {
    echo '
    <div class="alert alert-danger">
        <p>' . _("The administrator locked this poll, votes and comments are frozen, it's not possible to participate anymore.") . '</p>
        <p aria-hidden="true"><b>' . _('Legend:'). '</b> <span class="glyphicon glyphicon-ok"></span> =' . _('Yes') . ', <b>(<span class="glyphicon glyphicon-ok"></span>)</b> = ' . _('Ifneedbe') . ', <span class="glyphicon glyphicon-ban-circle"></span> = ' . _('No') . '</p>
    </div>';
} else {
    echo '
    <div class="alert alert-info">
        <p>' . _("If you want to vote in this poll, you have to give your name, choose the values that fit best for you and validate with the plus button at the end of the line.") . '</p>
        <p aria-hidden="true"><b>' . _('Legend:'). '</b> <span class="glyphicon glyphicon-ok"></span> =' . _('Yes') . ', <b>(<span class="glyphicon glyphicon-ok"></span>)</b> = ' . _('Ifneedbe') . ', <span class="glyphicon glyphicon-ban-circle"></span> = ' . _('No') . '</p>
    </div>';
}
echo'
    <div class="hidden row scroll-buttons" aria-hidden="true">
        <div class="btn-group pull-right">
            <button class="btn btn-sm btn-link scroll-left" title="' . _('Scroll to the left') . '">
                <span class="glyphicon glyphicon-chevron-left"></span>
            </button>
            <button class="btn  btn-sm btn-link scroll-right" title="' . _('Scroll to the right') . '">
                <span class="glyphicon glyphicon-chevron-right"></span>
            </button>
        </div>
    </div>

    <h3>'._('Votes of the poll ').'</h3>
    <div id="tableContainer" class="tableContainer">
        <table class="results">
            <caption class="sr-only">'._('Votes of the poll ').$title.'</caption>
            <thead>'. $thead . '</thead>
        <tbody>';

// Print poll results

//Usager pré-authentifié dans la liste?
$user_mod = false;

//affichage des resultats actuels
$somme[] = 0;
$compteur = 0;

while ($data = $user_studs->FetchNextObject(false)) {

    $ensemblereponses = $data->reponses;

    //affichage du nom
    $nombase=str_replace("°","'",$data->nom);
    echo '<tr>
<th class="bg-info">'.stripslashes($nombase).'</th>'."\n";

    // ligne d'un usager pré-authentifié
    $mod_ok = !( USE_REMOTE_USER && isset($_SERVER['REMOTE_USER']) ) || ($nombase == $_SESSION['nom']);
    $user_mod |= $mod_ok;

    // pour chaque colonne
    for ($k=0; $k < $nbcolonnes; $k++) {
        // on remplace les choix de l'utilisateur par une ligne de checkbox pour recuperer de nouvelles valeurs
        if ($compteur == $ligneamodifier) {

            $car = substr($ensemblereponses, $k , 1);

                // variable pour afficher la valeur cochée
                $car_html[0]='value="0"';$car_html[1]='value="1"';$car_html[2]='value="2"';
                switch ($car) {
                    case "1": $car_html[1]='value="1" checked';break;
                    case "2": $car_html[2]='value="2" checked';break;
                    default: $car_html[0]='value="0" checked';break;
                }

                echo '
                <td class="bg-info" headers="'.$td_headers[$k ].'">
                    <ul class="list-unstyled choice">
                        <li class="yes">
                            <input type="radio" id="y-choice-'.$k.'" name="choix'.$k.'" '.$car_html[1].' />
                            <label class="btn btn-default btn-xs" for="y-choice-'.$k.'" title="' . _('Vote "yes" for ') . $radio_title[$k] . '">
                                <span class="glyphicon glyphicon-ok"></span><span class="sr-only">' . _('Yes') . '</span>
                            </label>
                        </li>
                        <li class="ifneedbe">
                            <input type="radio" id="i-choice-'.$k.'" name="choix'.$k.'" '.$car_html[2].' />
                            <label class="btn btn-default btn-xs" for="i-choice-'.$k.'" title="' . _('Vote "ifneedbe" for ') . $radio_title[$k] . '">
                                (<span class="glyphicon glyphicon-ok"></span>)<span class="sr-only">' . _('Ifneedbe') . '</span>
                            </label>
                        </li>
                        <li class="no">
                            <input type="radio" id="n-choice-'.$k.'" name="choix'.$k.'" '.$car_html[0].'/>
                            <label class="btn btn-default btn-xs" for="n-choice-'.$k.'" title="' . _('Vote "no" for ') . $radio_title[$k] . '">
                                <span class="glyphicon glyphicon-ban-circle"></span><span class="sr-only">' . _('No') . '</span>
                            </label>
                        </li>
                    </ul>
                </td>'."\n";

        } else {
            $rbd = ($border[$k]) ? ' rbd' : '';
            $car = substr($ensemblereponses, $k, 1);
            switch ($car) {
                case "1": echo '<td class="bg-success text-success'.$rbd.'" headers="'.$td_headers[$k].'"><span class="glyphicon glyphicon-ok"></span><span class="sr-only"> ' . _('Yes') . '</span></td>'."\n";
                    if (isset($somme[$k]) === false) {
                        $somme[$k] = 0;
                    }
                    $somme[$k]++; break;
                case "2":  echo '<td class="bg-warning text-warning'.$rbd.'" headers="'.$td_headers[$k].'">(<span class="glyphicon glyphicon-ok"></span>)<span class="sr-only"> ' . _('Yes') . _(', ifneedbe') . '</span></td>'."\n"; break;
                default: echo '<td class="bg-danger'.$rbd.'" headers="'.$td_headers[$k].'"><span class="sr-only">' . _('No') . '</span></td>'."\n";
            }
        }
    }

    //a la fin de chaque ligne se trouve les boutons modifier
    if ($compteur != $ligneamodifier && ($dsondage->format=="A+"||$dsondage->format=="D+") && $mod_ok) {
        echo '
                <td>
                    <button type="submit" class="btn btn-link btn-sm" name="modifierligne'.$compteur.'" title="'. _('Edit the line:') .' '.stripslashes($nombase).'">
                        <span class="glyphicon glyphicon-pencil"></span><span class="sr-only">' . _('Edit') . '</span>
                    </button>
                </td>'."\n";
    }

    //demande de confirmation pour modification de ligne
    for ($i=0;$i<$nblignes;$i++) {
        if (isset($_POST["modifierligne$i"])) {
            if ($compteur == $i) {
                echo '<td style="padding:5px"><button type="submit" class="btn btn-success btn-xs" name="validermodifier'.$compteur.'" title="'. _('Save the choices') .' '.stripslashes($nombase).'">'. _('Save') .'</button></td>'."\n";
            }
        }
    }

    $compteur++;
    echo '</tr>'."\n";
}

// affichage de la ligne pour un nouvel utilisateur
if (( !(USE_REMOTE_USER && isset($_SERVER['REMOTE_USER'])) || !$user_mod) && $ligneamodifier==-1 && ($dsondage->format!="A-" && $dsondage->format!="D-")) {
    //affichage de la case vide de texte pour un nouvel utilisateur
    echo '<tr id="vote-form">
<td class="bg-info" style="padding:5px">
    <div class="input-group input-group-sm">
        <span class="input-group-addon"><span class="glyphicon glyphicon-user"></span></span>
        <input type="text" id="nom" name="nom" class="form-control" title="'. _('Your name') .'" placeholder="'. _('Your name') .'" />
    </div>
</td>'."\n";

    //une ligne de checkbox pour le choix du nouvel utilisateur
    for ($i = 0; $i < $nbcolonnes; $i++) {
        echo '
        <td class="bg-info" headers="'.$td_headers[$i].'">
            <ul class="list-unstyled choice">
                <li class="yes">
                    <input type="radio" id="y-choice-'.$i.'" name="choix'.$i.'" value="1" />
                    <label class="btn btn-default btn-xs" for="y-choice-'.$i.'" title="' . _('Vote "yes" for ') . $radio_title[$i] . '">
                        <span class="glyphicon glyphicon-ok"></span><span class="sr-only">' . _('Yes') . '</span>
                    </label>
                </li>
                <li class="ifneedbe">
                    <input type="radio" id="i-choice-'.$i.'" name="choix'.$i.'" value="2" />
                    <label class="btn btn-default btn-xs" for="i-choice-'.$i.'" title="' . _('Vote "ifneedbe" for ') . $radio_title[$i] . '">
                        (<span class="glyphicon glyphicon-ok"></span>)<span class="sr-only">' . _('Ifneedbe') . '</span>
                    </label>
                </li>
                <li class="no">
                    <input type="radio" id="n-choice-'.$i.'" name="choix'.$i.'" value="0" checked/>
                    <label class="btn btn-default btn-xs" for="n-choice-'.$i.'" title="' . _('Vote "no" for ') . $radio_title[$i] . '">
                        <span class="glyphicon glyphicon-ban-circle"></span><span class="sr-only">' . _('No') . '</span>
                    </label>
                </li>
            </ul>
        </td>'."\n";
    }

    // Affichage du bouton de formulaire pour inscrire un nouvel utilisateur dans la base
    echo '<td><button type="submit" class="btn btn-success btn-sm" name="boutonp" title="'. _('Save the choices') .'">'. _('Save') .'</button></td>
</tr>'."\n";

}

// Addition and Best choice
//affichage de la ligne contenant les sommes de chaque colonne
$tr_addition = '<tr id="addition"><td>'. _("Addition") .'</td>';
$meilleurecolonne = max($somme);
$compteursujet = 0;
$meilleursujet = '<ul style="list-style:none">';
for ($i = 0; $i < $nbcolonnes; $i++) {
    if (isset($somme[$i]) && $somme[$i] > 0 ) {
        if (in_array($i, array_keys($somme, max($somme)))){

            $tr_addition .= '<td><span class="glyphicon glyphicon-star text-warning"></span><span>'.$somme[$i].'</span></td>';

            $meilleursujet.= '<li><b>'.$radio_title[$i].'</b></li>';
            $compteursujet++;

        } else {
            $tr_addition .= '<td>'.$somme[$i].'</td>';
        }
    } else {
        $tr_addition .= '<td></td>';
    }
}
$tr_addition .= '<td></td></tr>';

$meilleursujet = str_replace("°", "'", $meilleursujet).'</ul>';
$vote_str = ($meilleurecolonne > 1) ? $vote_str = _('votes') : _('vote');

// Print Addition and Best choice
echo $tr_addition.'
        </tbody>
    </table>
    </div>
    <div class="row">'."\n";

if ($compteursujet == 1) {
    echo '
        <div class="col-sm-12"><h3>' . _("Best choice") . '</h3></div>
        <div class="col-sm-6 col-sm-offset-3 alert alert-success">
            <p><span class="glyphicon glyphicon-star text-warning"></span> ' . _("The best choice at this time is:") . '</p>
            ' . $meilleursujet . '
            <p>' . _("with") . ' <b>' . $meilleurecolonne . '</b> ' . $vote_str . '.</p>
        </div>'."\n";
} elseif ($compteursujet > 1) {
    echo '
        <div class="col-sm-12"><h3>' . _("Best choices") . '</h3></div>
        <div class="col-sm-6 col-sm-offset-3 alert alert-success">
            <p><span class="glyphicon glyphicon-star text-warning"></span> ' . _("The bests choices at this time are:") . '</p>
            ' . $meilleursujet . '
            <p>' . _("with") . ' <b>' . $meilleurecolonne . '</b> ' . $vote_str . '.</p>
        </div>'."\n";
}

echo '
    </div>
    <hr role="presentation" />';

// Comments
$sql = 'select * from comments where id_sondage='.$connect->Param('numsondage').' order by id_comment';
$sql = $connect->Prepare($sql);
$comment_user=$connect->Execute($sql, array($numsondage));

if ($comment_user->RecordCount() != 0) {
    echo '<div><h3>' . _("Comments of polled people") . '</h3>'."\n";

    while($dcomment = $comment_user->FetchNextObject(false)) {
        echo '
    <div class="comment">
        <b>'.stripslashes($dcomment->usercomment). ' :</b>
        <span class="comment">' . stripslashes(nl2br($dcomment->comment)) . '</span>
    </div>';
    }

    echo '</div>';
}

if ($dsondage->format!="A-" && $dsondage->format!="D-") {
echo '
        <div class="hidden-print alert alert-info">
            <div class="col-md-6 col-md-offset-3">
            <fieldset id="add-comment"><legend>' . _("Add a comment in the poll") . '</legend>
                <div class="form-group">
                    <p><label for="commentuser">'. _("Your name") .'</label><input type=text class="form-control" name="commentuser" id="commentuser" /></p>
                </div>
                <div class="form-group">
                    <p><label for="comment">'. _("Your comment") .'</label><br />
                    <textarea name="comment" id="comment" class="form-control" rows="2" cols="40"></textarea></p>
                </div>
                <p class="text-center"><input type="submit" name="ajoutcomment" value="'. _("Send the comment") .'" class="btn btn-success"></p>
            </fieldset>
            </div>
            <div class="clearfix"></div>
        </div>';
}

echo '
</form>';

bandeau_pied();
