<?php
/*******************************************************************************
  * @name: FormErreur
  * @note: Form error handler
  * @author: Jgauthi <github.com/jgauthi>, created at [5mars2007]
  * @version: 1.4.2
  * @Requirements:
    - PHP version >= 4+ (http://php.net)
  * @news:
    - testSelect
    - rapport($export_format = HTML | ALERT (javascript) | TXT)
    - Conversion Ereg_*() --> preg_*()

*******************************************************************************/

class FormErreur
{
    var $erreur = array();
    var $ob_start = false;

    //-- Constructeur -------------------------------------------------------------------------------------------
    function FormErreur() {}

    //-- Gestion des erreurs ------------------------------------------------------------------------------------
    function erreur($message, $id = '')
    {
        if (empty($id))
             $this -> erreur[] = $message;
        else $this -> erreur[$id] = $message;

        return false;
    }

    function nb_erreur()
    {
        return (count($this -> erreur));
    }

    function rapport($export_format = 'html')
    {
        if ($this -> nb_erreur() > 0)
        {
            $rapport = "Les erreurs suivantes ont été détectées: \n";
            $this -> erreur = array_unique($this -> erreur); // Supprimer les doublons

            foreach($this -> erreur as $erreur)
                $rapport .= "* $erreur\n";

            SWITCH($export_format)
            {
                case 'html':
                    return (nl2br(htmlentities($rapport)));
                    break;

                case 'alert' :
                    return ("window.alert('" .
                        str_replace(array("\r\n","\r","\n"), '\n', addslashes($rapport)) .
                        "');");
                    break;

                case 'javascript':
                    return ("<script language=\"javascript\">\nwindow.alert('" .
                        str_replace(array("\r\n","\r","\n"), '\n', addslashes($rapport)) .
                        "');\n</script>");
                    break;

                default: // Texte brute
                    return ($rapport);
                    break;
            }
        }
    }

    //-- Fonction de gestion ------------------------------------------------------------------------------------
    function testVide($nom, $value)
    {
        if (empty($value))
            return ($this -> erreur("Le champ '$nom' est vide", $nom));
        else
            return true;
    }

    function testNum($nom, $value)
    {
        if (!is_numeric($value))
            return ($this -> erreur("Le champ '$nom' n'est pas un nombre", $nom));
        else
            return true;
    }

    function testLogin($value, $table = '', $login_champ = 'login', $id_compte = '', $id_champ = 'id')
    {
        if ($this -> testVide('Login', $value))
        {
            // Nombre de caractère
            if (strlen($value) < 4 || strlen($value) > 50)
                return ($this -> erreur("Votre login doit-être constitué d'au moins 4 caractèrs et de 50 caractères au maximun", $login_champ));

            // Vérifier que le login existe déjà
            elseif (!empty($table) && !empty($login_champ))
            {
                // Action créer par défaut
                $req = "SELECT `$login_champ` FROM `$table`
						WHERE `$login_champ` = '$value'";

                // Action modifier
                if (!empty($id_compte) && !empty($id_champ) && is_numeric($id_compte))
                    $req .= " AND `$id_champ` !=  '$id_compte'";

                $req = mysql_query($req);

                if (mysql_num_rows($req) > 0)
                    return ($this -> erreur("Le login \"$value\" est déjà réservé, choississez-en un autre", 'Login'));
                else
                    return (true);
            }
            else
                return (true);
        }
        return false;
    }

    function testDate($nom, $value)
    {
        $regexp = '#^([0-9]{2})\/([0-9]{2})\/([0-9]{4})$#i';
        if (!preg_match($regexp, $value, $reg))
            return ($this -> erreur("Le format de la date '$nom' est incorrecte. Respecter le format: JJ/MM/YYYY", $nom));

        elseif(!checkdate((int)$reg[2], (int)$reg[1], $reg[3]))
            return ($this -> erreur("La date '$nom' fourni n'existe pas", $nom));

        else
            return true;
    }

    function testSelect($nom, $value, $select, $multi_select = false)
    {
        // Pas de multi-select, 1 seul option sélectionné
        if (!$multi_select)
        {
            if (empty($value) || !array_key_exists(MyStripSlashes($value), $select))
                return ($this -> erreur("Sélectionner votre $nom", $nom));
        }
        else
        {
            if (!is_array($value) || count($value) == 0)
                return ($this -> erreur("Sélectionner votre $nom", $nom));

            // Vérifier que les valeurs récupérées correspondent au select
            foreach($value as $search)
                if (empty($search) || !array_key_exists(MyStripSlashes($search), $nom))
                    return ($this -> erreur("Sélectionner votre $nom", $nom));

            // Si $multi_select est un chiffre,
            // --> vérifier que le nombre d'objets sélectionnée correspond à $multi_select
            if ($multi_select !== true && is_numeric($multi_select) && $multi_select != count($value))
                return ($this -> erreur("Le nombre de $nom sélectionné n'est pas correcte", $nom));
        }

        return true;
    }

    function testCivilite($nom, $value)
    {
        if (!preg_match('#^(M|Mme|Mlle)$#i', $value))
            return ($this -> erreur("Le choix de '$nom' n'est pas correcte, choississez M, Mme ou Mlle", $nom));
        else	return true;
    }

    function testTel($nom, $value)
    {
        /*
            ***** ATTENTION ******************
            Ne gère pas les numéros de téléphone étranger
            --
        */
        $regexp = '#^0[1-8][ .-]?([0-9]{2}[ .-]?){4}$#';
        if (!preg_match($regexp, $value))
            return ($this -> erreur("Le format du numéro de '$nom' est incorrecte: ".
                'respecter le format: 00 00 00 00 00 ou 0000000000', $nom));
        else
            return true;
    }

    function testCp($nom, $value)
    {
        /*
            Fonction pas encore développé pour l'instant
        */

        return ($this -> testVide($nom, $value));
    }

    function testEmail($nom, $value)
    {
        /*
            SOURCE:
            http://www.phpinfo.net/blogs/~jpdezelus/astuce/regex-pour-verifier-des-email.html
        */

        if ($this -> testVide($nom, $value))
        {
            $qtext = '[^\\x0d\\x22\\x5c\\x80-\\xff]';
            $dtext = '[^\\x0d\\x5b-\\x5d\\x80-\\xff]';
            $atom = '[^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c'.
                '\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+';
            $quoted_pair = '\\x5c[\\x00-\\x7f]';
            $domain_literal = "\\x5b($dtext|$quoted_pair)*\\x5d";
            $quoted_string = "\\x22($qtext|$quoted_pair)*\\x22";
            $domain_ref = $atom;
            $sub_domain = "($domain_ref|$domain_literal)";
            $word = "($atom|$quoted_string)";
            $domain = "$sub_domain(\\x2e$sub_domain)*";
            $local_part = "$word(\\x2e$word)*";
            $addr_spec = "$local_part\\x40$domain";

            if (!preg_match("!^$addr_spec$!", $value))
                return ($this -> erreur("le champ '$nom' est incorrecte, respecter le format: nom@serveur.domaine", $nom));
            else
                return true;
        }
        else
            return false;
    }

    function testPassword($pass1, $pass2)
    {
        if ($this -> testVide('Mot de passe', $pass1) && $this -> testVide('Mot de passe confirmation', $pass2))
        {
            if ($pass1 != $pass2)
                return ($this -> erreur("Le mot de passe et celui de confirmation sont différents", 'Mot de passe'));
            else	return true;
        }
        else
            return false;
    }

    function TestUpload($nom, $data, $doctype = '', $directory = '', $filename = '', $chmod = 0755)
    {
        if ($data['error'] == UPLOAD_ERR_OK && is_uploaded_file($data['tmp_name']))
        {
            // Nom du fichier sur le serveur
            if (empty($filename))
                $filename = $data['name'];

            if (!empty($doctype) && !preg_match("#$doctype#i", $data['type']))
                return($this -> erreur("Fichier '$nom': type de fichier incorrect", $nom));

            elseif (move_uploaded_file($data['tmp_name'], $directory.$filename))
            {
                chmod($directory.$filename, $chmod);
                return ($filename); // true
            }

            else
                return ($this -> erreur("Fichier '$nom': Erreur lors du transfert du fichier", $nom));
        }
        // Fichier mal uploadé
        else
        {
            // Chercher le bon message d'erreur
            SWITCH($data['error'])
            {
                case UPLOAD_ERR_OK:
                    $message = 'Le fichier soumis est incorrecte. Veuillez utiliser le formulaire approprié';
                    break;

                case UPLOAD_ERR_INI_SIZE: case UPLOAD_ERR_FORM_SIZE:
                $message = 'Le fichier est trop lourd';
                break;

                case UPLOAD_ERR_PARTIAL:
                    $message = 'Le fichier n\'a été que partiellement téléchargé';
                    break;

                case UPLOAD_ERR_NO_FILE:
                    $message = 'Aucun fichier n\'a été téléchargé';
                    break;

                default:
                    $message = 'Impossible de télécharger le fichier, erreur côté serveur';
                    break;
            }
            return ($this -> erreur("Fichier '$nom': $message", $nom));
        }
    }

    //-- Fonction de callback (expérimental) ------------------------------------------------------------------
    function callback()
    {
        if ($this -> ob_start)
            return (true);

        elseif (headers_sent())
            return (user_error('Error, headers already send. Callback cannot be enable.'));

        elseif($this -> nb_erreur() > 0)
        {
            $this -> ob_start = true;
            ob_start();
        }
        else
            return (false);
    }

    function callback_rapport($color = 'red')
    {
        if (!$this -> ob_start || ob_get_length() == 0)
            return (false);

        $content = ob_get_clean();
        foreach($this -> erreur as $id => $erreur)
        {
            $id2 = htmlentities($id);
            $erreur = htmlentities(str_replace("'$id'", '', $erreur));
            $content = preg_replace("#<label([^>]+)>($id|$id2)#i",
                "<label title=\"$erreur\" style=\"color: $color\"\\1>\\2",
                $content);
        }

        echo $content;
    }
}
