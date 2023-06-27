<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    local_mail
 * @author     Albert Gasset <albert.gasset@gmail.com>
 * @author     Marc Català <reskit@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addbcc'] = 'Afegeix a c/o';
$string['addcc'] = 'Afegeix a a/c';
$string['addrecipients'] = 'Afegeix destinataris';
$string['addto'] = 'Per a';
$string['advsearch'] = 'Cerca avançada';
$string['all'] = 'Tots';
$string['allcourses'] = 'Tots els cursos';
$string['applychanges'] = 'Aplica';
$string['assigntonewlabel'] = 'Etiqueta nova';
$string['attachments'] = 'Fitxers adjunts';
$string['attachnumber'] = '{$a} fitxers adjunts';
$string['back'] = 'Enrere';
$string['bcc'] = 'C/o';
$string['bulkmessage'] = 'Amb els usuaris seleccionats envia un correu intern...';
$string['cancel'] = 'Cancel·la';
$string['cannotcompose'] = 'No podeu redactar missatges perquè no esteu inscrit a cap curs.';
$string['cc'] = 'A/c';
$string['close'] = 'Tanca';
$string['colorblue'] = 'Blau';
$string['colorcyan'] = 'Cian';
$string['colorgray'] = 'Gris';
$string['colorgreen'] = 'Verd';
$string['colorindigo'] = 'Indi';
$string['colororange'] = 'Taronja';
$string['colorpink'] = 'Rosa';
$string['colorpurple'] = 'Porpra';
$string['colorred'] = 'Vermell';
$string['colorteal'] = 'Xarxet';
$string['coloryellow'] = 'Groc';
$string['compose'] = 'Redacta';
$string['configcoursebadges'] = 'Etiquetes de curs';
$string['configcoursebadgesdesc'] = 'Estableix el tipus de nom de curs mostrat als missatges.';
$string['configcoursebadgeslength'] = 'Longitud de les etiquetes de curs.';
$string['configcoursebadgeslengthdesc'] = 'Limita la longitud de les etiquetes de curs a aquest nombre aproximat de caràcters.';
$string['configcoursetrays'] = 'Safates de curs';
$string['configcoursetraysdesc'] = 'Estableix quins cursos es mostren als menús.';
$string['configcoursetraysname'] = 'Nom de les safates de curs';
$string['configcoursetraysnamedesc'] = 'Estableix el tipus de nom de curs que es mostra als menús.';
$string['configenablebackup'] = 'Còpia de seguretat / restauració';
$string['configenablebackupdesc'] = 'Habilita les còpies de seguretat i la restauració de missatges de correu i etiquetes.';
$string['configfilterbycourse'] = 'Filtre por curs';
$string['configfilterbycoursedesc'] = 'Estableix el tipus de nom de curs utilitzat al filtre per curs.';
$string['configglobaltrays'] = 'Safates globals';
$string['configglobaltraysdesc'] = 'Estableix quines safates globals es mostren als menús. La safata d\'entrada sempre és visible.';
$string['configincrementalsearch'] = 'Cerca instantània';
$string['configincrementalsearchdesc'] = 'Habilita la visualització de resultats mentre l\'usuari escriu al quadre de cerca.';
$string['configincrementalsearchlimit'] = 'Límit de la cerca ràpida';
$string['configincrementalsearchlimitdesc'] = 'Estableix el nombre màxim de missatges recents inclosos a la cerca instantània. Augmentar aquest nombre pot tenir un impacte negatiu al rendiment de la base de dades.';
$string['configmaxattachments'] = 'Nombre màxim de fitxers adjunts';
$string['configmaxattachmentsize'] = 'Mida màxima dels fitxers adjunts';
$string['continue'] = 'Continua';
$string['courses'] = 'Cursos';
$string['courseswithunreadmessages'] = 'Cursos amb missatges no llegits';
$string['create'] = 'Crea';
$string['delete'] = 'Suprimeix';
$string['deleteforever'] = 'Suprimeix definitivament';
$string['deletelabel'] = 'Suprimeix l\'etiqueta';
$string['discard'] = 'Descarta';
$string['downloadall'] = 'Baixa\'ls tots';
$string['draft'] = 'Esborrany';
$string['drafts'] = 'Esborranys';
$string['editlabel'] = 'Edita l\'etiqueta';
$string['emptycoursefilterresults'] = 'Cap curs coincideix amb el text introduït';
$string['emptyrecipients'] = 'No hi ha destinataris.';
$string['emptytrash'] = 'Buida la paperera';
$string['emptytrashconfirm'] = 'Esteu segur que voleu suprimir definitivament tots els missatges de la paperera?';
$string['error'] = 'Error';
$string['erroremptycourse'] = 'Indiqueu un curs.';
$string['erroremptylabelname'] = 'Indiqueu un nom d\'etiqueta.';
$string['erroremptyrecipients'] = 'Indiqueu un destinatari com a mínim.';
$string['erroremptysubject'] = 'Indiqueu l\'assumpte.';
$string['errorinvalidcolor'] = 'Color no vàlid';
$string['errorlabelnotfound'] = 'No s\'ha trobat l\'etiqueta';
$string['errormessagenotfound'] = 'No s\'ha trobat el missatge';
$string['errorrepeatedlabelname'] = 'El nom d\'etiqueta ja existeix';
$string['filterbycourse'] = 'Filtra per curs';
$string['filterbydate'] = 'Data';
$string['forward'] = 'Reenvia';
$string['from'] = 'De';
$string['hasattachments'] = '(Missatge amb fitxers adjunts)';
$string['inbox'] = 'Safata d\'entrada';
$string['labelcolor'] = 'Color';
$string['labeldeleteconfirm'] = 'Esteu segur que voleu suprimir definitivament l\'etiqueta «{$a}»?';
$string['labelname'] = 'Nom';
$string['labels'] = 'Etiquetes';
$string['mail:addinstance'] = 'Afegeix un correu nou';
$string['mail:mailsamerole'] = 'Envia correus als usuaris amb el mateix rol';
$string['mail:usemail'] = 'Utilitza el correu';
$string['mailmenu'] = 'Menú del correu';
$string['mailupdater'] = 'Actualització de correu';
$string['markasread_help'] = 'Si està activat, els missatges rebuts es marcaran com a llegits';
$string['markasread'] = 'Marca com a llegit';
$string['markasstarred'] = 'Marca com a destacat';
$string['markasunread'] = 'Marca com a no llegit';
$string['markasunstarred'] = 'Marca com a no destacat';
$string['message'] = 'Missatge';
$string['messagedeleteconfirm'] = 'Esteu segur que voleu suprimir definitivament els missatges seleccionats?';
$string['messagelist'] = 'Llista de missatges';
$string['messagerestoreconfirm'] = 'Esteu segur que voleu restaurar els missatges seleccionats?';
$string['messageprovider:mail'] = 'Notificació de correu';
$string['messages'] = 'Missatges';
$string['messagesperpage'] = 'Missatges per pàgina';
$string['moreactions'] = 'Més';
$string['mymail'] = 'El meu correu';
$string['newlabel'] = 'Etiqueta nova';
$string['nextmessage'] = 'Missatge següent';
$string['nextpage'] = 'Pàgina següent';
$string['nocolor'] = 'Sense color';
$string['nolabels'] = 'No hi ha cap etiqueta.';
$string['nomessages'] = 'No hi ha cap missatge.';
$string['nomessageserror'] = 'Per realitzar aquesta acció cal seleccionar algun missatge';
$string['nomessagesfound'] = 'No s\'han trobat missatges';
$string['none'] = 'Cap';
$string['norecipient'] = '(sense destinataris)';
$string['noselectedmessages'] = 'Cap missatge seleccionat';
$string['nosubject'] = '(sense assumpte)';
$string['notificationbody'] = '- De: {$a->user}

- Assumpte: {$a->subject}

{$a->content}';
$string['notificationbodyhtml'] = '<p>De: {$a->user}</p><p>Assumpte: <a href="{$a->url}">{$a->subject}</a></p><p>{$a->content}</p>';
$string['notificationpref'] = 'Notificacions d\'enviament';
$string['notificationsubject'] = 'Missatge de correu electrònic nou a {$a}';
$string['notingroup'] = 'No esteu a cap grup';
$string['pagingmultiple'] = '{$a->first}-{$a->last} de {$a->total}';
$string['pagingsearch'] = '{$a->first}-{$a->last}';
$string['pagingsingle'] = '{$a->index} de {$a->total}';
$string['perpage'] = 'Mostra {$a} missatges';
$string['pluginname'] = 'Correu';
$string['pluginnotinstalled'] = 'El connector Correu no està instal·lat o actualitzat correctament.';
$string['preferences'] = 'Preferències';
$string['previousmessage'] = 'Missatge anterior';
$string['previouspage'] = 'Pàgina anterior';
$string['read'] = 'Llegits';
$string['references'] = 'Referències';
$string['removelabel'] = 'Elimina l\'etiqueta';
$string['reply'] = 'Respon';
$string['replyall'] = 'Respon a tothom';
$string['restore'] = 'Restaura';
$string['save'] = 'Desa';
$string['search'] = 'Cerca';
$string['searchallmessages'] = 'Cerca tots els missatges';
$string['searchbyattach'] = 'Conté fitxers adjunts';
$string['searchbyunread'] = 'Només sense llegir';
$string['select'] = 'Selecciona';
$string['send'] = 'Envia';
$string['sendmessage'] = 'Envia un missatge';
$string['sentmail'] = 'Enviats';
$string['setlabels'] = 'Etiquetes';
$string['shortaddbcc'] = 'C/o';
$string['shortaddcc'] = 'A/c';
$string['shortaddto'] = 'Per a';
$string['showlabelmessages'] = 'Mostra els missatges amb l\'etiqueta «{$a}»';
$string['showrecentmessages'] = 'Mostra els missatges més nous';
$string['smallmessage'] = '{$a->user} us ha enviat un missatge de correu';
$string['starred'] = 'Destacat';
$string['starredmail'] = 'Destacats';
$string['subject'] = 'Assumpte';
$string['to'] = 'Per a';
$string['togglefilterresults'] = 'Mostrar/oculta els resultats del filtre';
$string['togglemailmenu'] = 'Mostra/amaga el menú del correu';
$string['toomanyrecipients'] = 'La cerca conté massa resultats';
$string['trash'] = 'Paperera';
$string['trays'] = 'Safates';
$string['undo'] = 'Desfés';
$string['undodeletemany'] = 'S\'han mogut {$a} missatges a la paperera';
$string['undodeleteone'] = 'S\'ha mogut un missatge a la paperera';
$string['undorestoremany'] = 'S\'han restaurat {$a} missatges';
$string['undorestoreone'] = 'S\'ha restaurat un missatge';
$string['unread'] = 'Sense llegir';
$string['unstarred'] = 'Sense destacar';
