<?php
//-------------------------------------------------
// Fonction gestion des slashes avant entré DB
//-------------------------------------------------

/**
 * @param string $chaine
 * @return string
 */
function MyAddSlashes($chaine)
{
    return( get_magic_quotes_gpc() == 1 ?
        $chaine :
        addslashes($chaine) );
}

/**
 * @param string $chaine
 * @return string
 */
function MyStripSlashes($chaine)
{
    return( get_magic_quotes_gpc() == 1 ?
        stripslashes($chaine) :
        $chaine );
}

/**
 * @param string|int|float|null $content
 * @return string
 */
function sql_data($content)
{
    if (empty($content))
            return ('NULL');
    else	return ('\''. MyAddSlashes(trim($content)) .'\'');
}
