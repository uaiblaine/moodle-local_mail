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
$string['allgroups'] = 'Tots els grups';
$string['allroles'] = 'Tots els rols';
$string['allusers'] = 'Tots els usuaris';
$string['applychanges'] = 'Aplica';
$string['assigntonewlabel'] = 'Etiqueta nova';
$string['attachments'] = 'Fitxers adjunts';
$string['attachnumber'] = '{$a} fitxers adjunts';
$string['back'] = 'Enrere';
$string['bcc'] = 'C/o';
$string['cancel'] = 'Cancel·la';
$string['cannotcompose'] = 'No podeu redactar missatges perquè no esteu inscrit a cap curs.';
$string['cannotsendmailtouser'] = 'No podeu enviar correu a aquest usuari en aquest curs.';
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
$string['configmaxattachments'] = 'Nombre de fitxers adjunts';
$string['configmaxattachmentsdesc'] = 'Estableix el nombre màxim de fitxers adjunts per missatge.';
$string['configmaxattachmentsize'] = 'Mida dels fitxers adjunts';
$string['configmaxattachmentsizedesc'] = 'Estableix la mida màxima dels fitxers adjunts per missatge.';
$string['configmaxrecipients'] = 'Nombre de destinataris';
$string['configmaxrecipientsdesc'] = 'Estableix el nombre màxim de destinataris permesos per missatge.';
$string['configusersearchlimit'] = 'Límit de la cerca d\'usuaris';
$string['configusersearchlimitdesc'] = 'Estableix el nombre màxim de resultats mostrats en la cerca d\'usuaris.';
$string['continue'] = 'Continua';
$string['course'] = 'Curs';
$string['courses'] = 'Cursos';
$string['courseswithunreadmessages'] = 'Cursos amb missatges no llegits';
$string['create'] = 'Crea';
$string['date'] = 'Data';
$string['delete'] = 'Suprimeix';
$string['deleteforever'] = 'Suprimeix definitivament';
$string['deletelabel'] = 'Suprimeix l\'etiqueta';
$string['discard'] = 'Descarta';
$string['downloadall'] = 'Baixa\'ls tots';
$string['draft'] = 'Esborrany';
$string['drafts'] = 'Esborranys';
$string['draftsaved'] = 'S\'ha desat l\'esborrany';
$string['editlabel'] = 'Edita l\'etiqueta';
$string['emptycoursefilterresults'] = 'Cap curs coincideix amb el text introduït';
$string['emptyrecipients'] = 'No hi ha destinataris.';
$string['emptytrash'] = 'Buida la paperera';
$string['emptytrashconfirm'] = 'Esteu segur que voleu suprimir definitivament tots els missatges de la paperera?';
$string['error'] = 'Error';
$string['errorcoursenotfound'] = 'No s\'ha trobat el curs';
$string['erroremptycourse'] = 'Indiqueu un curs.';
$string['erroremptylabelname'] = 'Introduïu un nom d\'etiqueta.';
$string['erroremptyrecipients'] = 'Afegiu un destinatari com a mínim.';
$string['erroremptysubject'] = 'Introduïu l\'assumpte.';
$string['errorinvalidcolor'] = 'Color no vàlid';
$string['errorinvalidrecipients'] = 'Un o diversos dels destinataris no són vàlids.';
$string['errorlabelnotfound'] = 'No s\'ha trobat l\'etiqueta';
$string['errormessagenotfound'] = 'No s\'ha trobat el missatge';
$string['errornocourses'] = 'No teneu permís per enviar o rebre correu en cap curs.';
$string['errorrepeatedlabelname'] = 'El nom d\'etiqueta ja existeix';
$string['errortoomanyrecipients'] = 'El missatge supera el límit permès de {$a} destinataris.';
$string['filterbycourse'] = 'Filtra per curs';
$string['filterbydate'] = 'Data';
$string['forward'] = 'Reenvia';
$string['forwardedmessage'] = 'Missatge reenviat';
$string['from'] = 'De';
$string['hasattachments'] = '(Missatge amb fitxers adjunts)';
$string['inbox'] = 'Safata d\'entrada';
$string['labelcolor'] = 'Color';
$string['labeldeleteconfirm'] = 'Esteu segur que voleu suprimir definitivament l\'etiqueta «{$a}»?';
$string['labelname'] = 'Nom';
$string['labels'] = 'Etiquetes';
$string['locked'] = 'Bloquejat';
$string['mail:addinstance'] = 'Afegeix un correu nou';
$string['mail:mailsamerole'] = 'Envia correus als usuaris amb el mateix rol';
$string['mail:usemail'] = 'Utilitza el correu';
$string['markmessageasread'] = 'Marca el missatge com a llegit';
$string['markasread'] = 'Marca com a llegit';
$string['markasstarred'] = 'Marca com a destacat';
$string['markasunread'] = 'Marca com a no llegit';
$string['markasunstarred'] = 'Marca com a no destacat';
$string['message'] = 'Missatge';
$string['messagesent'] = 'S\'ha enviat el missatge';
$string['messagedeleteconfirm'] = 'Esteu segur que voleu suprimir definitivament els missatges seleccionats?';
$string['messagelist'] = 'Llista de missatges';
$string['messagerestoreconfirm'] = 'Esteu segur que voleu restaurar els missatges seleccionats?';
$string['messageprovider:mail'] = 'Notificació de correu';
$string['messages'] = 'Missatges';
$string['messagesperpage'] = 'Missatges per pàgina';
$string['moreactions'] = 'Més';
$string['mymail'] = 'El meu correu';
$string['newlabel'] = 'Etiqueta nova';
$string['newmail'] = 'Correu nou';
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
$string['notifications'] = 'Notificacions';
$string['notificationsmallmessage'] = '{$a->user} us ha enviat un missatge al curs {$a->course}';
$string['notificationsubject'] = 'Nou correu a {$a}';
$string['notingroup'] = 'No esteu a cap grup';
$string['nousersfound'] = 'No s\'han trobat usuaris.';
$string['pagingmultiple'] = '{$a->first}-{$a->last} de {$a->total}';
$string['pagingsearch'] = '{$a->first}-{$a->last}';
$string['pagingsingle'] = '{$a->index} de {$a->total}';
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
$string['sendmail'] = 'Envia correu';
$string['sentmail'] = 'Enviats';
$string['setlabels'] = 'Etiquetes';
$string['shortaddbcc'] = 'C/o';
$string['shortaddcc'] = 'A/c';
$string['shortaddto'] = 'Per a';
$string['showlabelmessages'] = 'Mostra els missatges amb l\'etiqueta «{$a}»';
$string['showrecentmessages'] = 'Mostra els missatges més nous';
$string['starred'] = 'Destacat';
$string['starredmail'] = 'Destacats';
$string['subject'] = 'Assumpte';
$string['to'] = 'Per a';
$string['togglefilterresults'] = 'Mostrar/oculta els resultats del filtre';
$string['togglemailmenu'] = 'Mostra/amaga el menú del correu';
$string['toomanyrecipients'] = 'La cerca conté massa resultats';
$string['toomanyusersfound'] = 'S\'han trobat més usuaris dels que es poden mostrar. Introduïu un text o seleccioneu un rol o un grup per restringir la cerca.';
$string['trash'] = 'Paperera';
$string['trays'] = 'Safates';
$string['undo'] = 'Desfés';
$string['undodeletemany'] = 'S\'han mogut {$a} missatges a la paperera';
$string['undodeleteone'] = 'S\'ha mogut un missatge a la paperera';
$string['undorestoremany'] = 'S\'han restaurat {$a} missatges';
$string['undorestoreone'] = 'S\'ha restaurat un missatge';
$string['unread'] = 'Sense llegir';
$string['unstarred'] = 'Sense destacar';
$string['viewmessage'] = 'Visualitza el missatge';
