<?php
//-------------------------------------------------
// Fonction gestion des slashes avant entré DB
//-------------------------------------------------
function MyAddSlashes($chaine)
{
    return( get_magic_quotes_gpc() == 1 ?
        $chaine :
        addslashes($chaine) );
}

function MyStripSlashes($chaine)
{
    return( get_magic_quotes_gpc() == 1 ?
        stripslashes($chaine) :
        $chaine );
}

function sql_data($content)
{
    if (empty($content))
        return ('NULL');
    else	return ('\''. MyAddSlashes(trim($content)) .'\'');
}
