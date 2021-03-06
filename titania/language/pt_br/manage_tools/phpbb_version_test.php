<?php
/**
*
* @package Titania
* @version $Id$
* @copyright (c) 2008 phpBB Customisation Database Team
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2
* Tradução feita e revisada pela Equipe phpBB Brasil <http://www.phpbbrasil.com.br>!
*
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine
//
// Some characters you may want to copy&paste:
// ’ » “ ” …
//

$lang = array_merge($lang, array(
	'NEW_PHPBB_VERSION'				=> 'Nova versão do phpBB',
	'NEW_PHPBB_VERSION_EXPLAIN'		=> 'Nova versão do phpBB a ser adicionada à lista de suporte das revisões.',
	'NO_REVISIONS_UPDATED'			=> 'Nenhuma revisão foi atualizada dadas as limitações.',
	'NO_VERSION_SELECTED'			=> 'Você deve informar uma versão correta do phpBB. Ex.: 3.0.7 ou 3.0.7-PL1.',

	'PHPBB_VERSION_TEST'			=> 'Testar suporte à versão do phpBB para revisão da modificação',

	'REVISIONS_ADDED_TO_QUEUE'		=> '%s revisões foram adicionadas para a fila de testes no Automod.',

	'VERSION_RESTRICTION'			=> 'Restrição de versão',
	'VERSION_RESTRICTION_EXPLAIN'	=> 'Restringe o suporte à nova versão somente para versões selecionadas.',
));
