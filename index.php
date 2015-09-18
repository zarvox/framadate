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

use Framadate\Utils;

include_once __DIR__ . '/app/inc/init.php';

if (is_readable('bandeaux_local.php')) {
    include_once('bandeaux_local.php');
} else {
    include_once('bandeaux.php');
}

session_start();

// Sandstorm: redirect to appropriate page depending on
// which type of grain this is and whether a poll has actually
// been created yet or not

// Load grain type.
$grain_type = trim(file_get_contents("/var/action.txt"));

// See if a poll/calendar has been made yet, and load IDs if so.
$sql = 'SELECT * FROM sondage LIMIT 1';
$sondages = $connect->Execute($sql);

$count = 0;
$admin_id = null;
$sondage_id = null;

while ($data = $sondages->FetchNextObject(false)) {
    $admin_id = $data->id_sondage_admin;
    $sondage_id = $data->id_sondage;
    $count = $count + 1;
}

if ($count == 0) {
    if ($grain_type == "schedule") {
        header("Location:infos_sondage.php?choix_sondage=date");
        exit();
    }
    if ($grain_type == "poll") {
        header("Location:infos_sondage.php?choix_sondage=autre");
        exit();
    }
} else {
    // Check permissions and redirect to either adminstuds or studs, depending on permission level
    $perms = explode(",", $_SERVER["HTTP_X_SANDSTORM_PERMISSIONS"]);
    $isAdmin = in_array("admin", $perms, true);
    if ($isAdmin) {
        header("Location:adminstuds.php?sondage=" . $admin_id);
        exit();
    } else {
        header("Location:studs.php?sondage=" . $sondage_id);
        exit();
    }
}

// affichage de la page
Utils::print_header( _("Home") );
bandeau_titre(_("Make your polls"));

echo '
        <div class="row">
            <div class="col-md-6 text-center">
                <p class="home-choice"><a href="'.Utils::get_server_name().'infos_sondage.php?choix_sondage=date" class="opacity" role="button">
                    <img class="img-responsive center-block" src="'.Utils::get_server_name().'images/date.png" alt="" />
                    <br /><span class="btn btn-primary btn-lg"><span class="glyphicon glyphicon-calendar"></span>
                    '. _('Schedule an event') . '</span>
                </a></p>
            </div>
            <div class="col-md-6 text-center">
                <p class="home-choice"><a href="'.Utils::get_server_name().'infos_sondage.php?choix_sondage=autre" class="opacity" role="button">
                    <img alt="" class="img-responsive center-block" src="'.Utils::get_server_name().'images/classic.png" />
                    <br /><span class="btn btn-info btn-lg"><span class="glyphicon glyphicon-stats"></span>
                    '. _('Make a classic poll') . '</span>
                </a></p>
            </div>
        </div>
        <hr  role="presentation" />
        <div class="row">';
        $nbcol = $config['show_what_is_that'] + $config['show_the_software'] + $config['show_cultivate_your_garden'];
        if ($nbcol > 0){
            $colmd = 12/$nbcol; // 3 =>col-md-4, 2 =>col-md-6, 1 =>col-md-12.
        }
            if($config['show_what_is_that'] == true){
                echo '<div class="col-md-'.$colmd.'">
                <h3>'. _('What is that?') . '</h3>
                <p class="text-center" role="presentation"><span class="glyphicon glyphicon-question-sign" style="font-size:50px"></span></p>
                <p>'. _('Framadate is an online service for planning an appointment or make a decision quickly and easily. No registration is required.') .'</p>
                <p>'. _('Here is how it works:') . '</p>
                <ol>
                    <li>'. _('Make a poll') . '</li>
                    <li>'. _('Define dates or subjects to choose') . '</li>
                    <li>'. _('Send the poll link to your friends or colleagues') . '</li>
                    <li>'. _('Discuss and make a decision') . '</li>
                </ol>
                <p>'. _('Do you want to ') . '<a href="' . Utils::getUrlSondage('aqg259dth55iuhwm').'">'. _("view an example?") .'</a></p>
                </div>';
            }

            if($config['show_the_software'] == true){
                echo '<div class="col-md-'.$colmd.'">
                <h3>'. _('The software') .'</h3>
                <p class="text-center" role="presentation"><span class="glyphicon glyphicon-cloud" style="font-size:50px"></span></p>
                <p>'. _('Framadate was initially based on '). '<a href="https://sourcesup.cru.fr/projects/studs/">Studs</a>'. _(' a software developed by the University of Strasbourg. Today, it is devevoped by the association Framasoft') .'.</p>
                <p>'. _('This software needs javascript and cookies enabled. It is compatible with the following web browsers:') .'</p>
                <ul>
                    <li>Microsoft Internet Explorer 9+</li>
                    <li>Google Chrome 19+</li>
                    <li>Firefox 12+</li>
                    <li>Safari 5+</li>
                    <li>Opera 11+</li>
                </ul>
                <p>'. _('It is governed by the ').'<a href="http://www.cecill.info">'. _('CeCILL-B license').'</a>.</p>
                </div>';
            }

            if($config['show_cultivate_your_garden'] == true){
                echo '<div class="col-md-'.$colmd.'">
                <h3>'. _('Cultivate your garden') .'</h3>
                <p class="text-center" role="presentation"><span class="glyphicon glyphicon-tree-deciduous" style="font-size:50px"></span></p>
                <p>'. _('To participate in the software development, suggest improvements or simply download it, please visit ') .'<a href="https://git.framasoft.org/framasoft/framadate">'._('the development site').'</a>.</p>
                <br />
                <p>'. _('If you want to install the software for your own use and thus increase your independence, we help you on:') .'</p>
                <p class="text-center"><a href="http://framacloud.org/cultiver-son-jardin/installation-de-framadate/" class="btn btn-success"><span class="glyphicon glyphicon-tree-deciduous"></span> framacloud.org</a></p>
                </div>';
            }
        echo '</div>'."\n";

bandeau_pied();
