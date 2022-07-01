<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}

$deamonRunning = eufy::deamonRunning();

?>
<form class="form-horizontal">
  <fieldset>
        <!--<div class="form-group">
            <label class="col-lg-4 control-label">{{Email Eufy}}</label>
            <div class="col-lg-2">
                <input class="configKey form-control" data-l1key="userId" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Mot de passe Eufy}}</label>
            <div class="col-lg-2">
                <input class="configKey form-control" type="password" data-l1key="userPassword" />
            </div>
        </div>-->
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Ip Docker (container Eufy-Security-WS)}}</label>
            <div class="col-lg-2">
                <input class="configKey form-control" data-l1key="containerip" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Port Docker (container Eufy-Security-WS)}}</label>
            <div class="col-lg-2">
                <input class="configKey form-control" data-l1key="containerPort" />
            </div>
        </div>
        <?php
          echo '<div class="form-group">';
          echo '<label class="col-sm-4 control-label">{{Communication avec Eufy}}</label>';
          if (!$deamonRunning) {
            echo '<div class="col-sm-1"><span class="label label-danger">NOK</span></div>';
          } else {
            echo '<div class="col-sm-1"><span class="label label-success">OK</span></div>';
          }
          echo '</div>';
      ?>

  </fieldset>
</form>
