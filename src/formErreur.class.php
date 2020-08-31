<?php
/*******************************************************************************
  * @name: FormErreur
  * @note: Form error handler
  * @author: Jgauthi <github.com/jgauthi>, created at [5mars2007]
  * @version: 1.6.5
  * @Requirements:
    - PHP version >= 5+ (http://php.net)
  * @news:
    - Ajout gestion des traductions
    - testSelect

*******************************************************************************/

class FormErreur
{
    protected $erreur = array();
    protected $ob_start = false;
    protected $function_trad = null;
    protected $function_erreur = null;

    //-- Constructeur ------------------------------------------------------------------------
    public function __construct()
    {
        // A défaut d'une fonction de traduction, utiliser le gestionnaire d'erreur classique
        //$this->set_trad_function(array($this, 'erreur'));

        // Définir une fonction d'erreur personnalisé
        $this->set_function_error(array($this, 'erreur_default_function'));
    }

    /*public function set_trad_function($function)
    {
        $this->function_trad = $function;
    }*/

    public function set_function_error($function)
    {
        $this->function_erreur = $function;
    }


    //-- Gestion des erreurs ------------------------------------------------------------------
    public function erreur($message, $id = null)
    {
        if(empty($id))
            $this->erreur[] = $message;
        else 	$this->erreur[$id] = $message;

        return call_user_func_array($this->function_erreur, array($message, $id));
    }

    public function erreur_default_function($message, $id) { return false; }

    public function nb_erreur()
    {
        return count($this->erreur);
    }

    public function rapport($export_format = 'html')
    {
        if($this->nb_erreur() == 0)
            return null;

        $rapport = "Les erreurs suivantes ont été détectées: \n";
        $this->erreur = array_unique($this->erreur); // Supprimer les doublons

        foreach($this->erreur as $erreur)
            $rapport .= "* $erreur\n";

        SWITCH($export_format)
        {
            case 'html':
                return nl2br(htmlentities($rapport));
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
                return $rapport;
                break;
        }
    }

    //-- Fonction de gestion ------------------------------------------------------------------------------------
    public function testVide($nom, &$value)
    {
        if(!isset($value) || $value === null || trim($value) == '')
            return ($this->erreur("Le champ '$nom' est vide", $nom));
        else 	return true;
    }

    public function testNum($nom, &$value)
    {
        if(!isset($value) || !is_numeric($value))
            return ($this->erreur("Le champ '$nom' n'est pas un nombre", $nom));
        else 	return true;
    }

    public function testLogin($value, $table = null, $login_champ = 'login', $id_compte = null, $id_champ = 'id')
    {
        if(!$this->testVide('Login', $value))
            return false;

        // Nombre de caractère
        if(strlen($value) < 4 || strlen($value) > 50)
            return $this->erreur("Votre login doit-être constitué d'au moins 4 caractèrs et de 50 caractères au maximun", $login_champ);

        // Vérifier que le login existe déjà
        elseif(!empty($table) && !empty($login_champ))
        {
            // Action créer par défaut
            $req = "SELECT `$login_champ` FROM `$table`
					WHERE `$login_champ` = '$value'";

            // Action modifier
            if(!empty($id_compte) && !empty($id_champ) && is_numeric($id_compte))
                $req .= " AND `$id_champ` !=  '$id_compte'";

            $req = mysql_query($req);

            if(mysql_num_rows($req) > 0)
                return ($this->erreur("Le login \"$value\" est déjà réservé, choississez-en un autre", 'Login'));
            else return true;
        }
        else return true;
    }


    public function testDate($nom, $value)
    {
        $regexp = '#^([0-9]{2})\/([0-9]{2})\/([0-9]{4})$#i';
        if(!preg_match($regexp, $value, $reg))
            return ($this->erreur("Le format de la date '$nom' est incorrecte. Respecter le format: JJ/MM/YYYY", $nom));

        elseif(!checkdate((int)$reg[2], (int)$reg[1], $reg[3]))
            return ($this->erreur("La date '$nom' fourni n'existe pas", $nom));

        else return true;
    }


    public function testSelect($nom, $value, $select, $multi_select = false)
    {
        // Pas de multi-select, 1 seul option sélectionné
        if(!$multi_select)
        {
            if(!$this->testVide($nom, $value) || !array_key_exists(MyStripSlashes($value), $select))
                return ($this->erreur("Sélectionner votre $nom", $nom));
        }
        else
        {
            if(!is_array($value) || count($value) == 0)
                return ($this->erreur("Sélectionner votre $nom", $nom));

            // Vérifier que les valeurs récupérées correspondent au select
            foreach($value as $search)
                if(!$this->testVide($nom, $value) || !array_key_exists(MyStripSlashes($search), $nom))
                    return $this->erreur("Sélectionner votre $nom", $nom);

            // Si $multi_select est un chiffre,
            // --> vérifier que le nombre d'objets sélectionnée correspond à $multi_select
            if($multi_select !== true && is_numeric($multi_select) && $multi_select != count($value))
                return $this->erreur("Le nombre de $nom sélectionné n'est pas correcte", $nom);
        }

        return true;
    }

    public function testCivilite($nom, $value)
    {
        if(!preg_match('#^(M|Mme|Mlle)$#i', $value))
            return ($this->erreur("Le choix de '$nom' n'est pas correcte, choississez M, Mme ou Mlle", $nom));
        else return true;
    }

    public function testTel($nom, $value)
    {
        // [ATTENTION] Ne gère pas les numéros de téléphone étranger
        $regexp = '#^0[1-8][ .-]?([0-9]{2}[ .-]?){4}$#';

        if(!preg_match($regexp, $value))
            return ($this->erreur("Le format du numéro de '$nom' est incorrecte: ".
                'respecter le format: 00 00 00 00 00 ou 0000000000', $nom));
        else return true;
    }

    // Fonction pas encore développé pour l'instant
    public function testCp($nom, $value)
    {
        return $this->testVide($nom, $value);
    }

    public function testEmail($nom, $value)
    {
        if(!$this->testVide($nom, $value))
            return false;

        // SOURCE:
        //	http://www.phpinfo.net/blogs/~jpdezelus/astuce/regex-pour-verifier-des-email.html
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

        if(!preg_match("!^$addr_spec$!", $value))
            return ($this->erreur("le champ '$nom' est incorrecte, respecter le format: nom@serveur.domaine", $nom));
        else return true;
    }

    public function testPassword($pass1, $pass2)
    {
        if(!$this->testVide('Mot de passe', $pass1))
            return false;

        elseif(!$this->testVide('Mot de passe confirmation', $pass2))
            return false;

        elseif($pass1 != $pass2)
            return $this->erreur("Le mot de passe et celui de confirmation sont différents", 'Mot de passe');

        else return true;
    }


    public function testUpload($nom, $data, $doctype = null, $directory = null, $filename = null, $chmod = 0755)
    {
        // Fichier mal uploadé
        if($data['error'] != UPLOAD_ERR_OK || !is_uploaded_file($data['tmp_name']))
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
            return $this->erreur("Fichier '$nom': $message", $nom);
        }


        // Nom du fichier sur le serveur
        if(empty($filename)) $filename = $data['name'];


        if(!empty($doctype) && !preg_match("#$doctype#i", $data['type']))
            return $this->erreur("Fichier '$nom': type de fichier incorrect", $nom);

        elseif(!move_uploaded_file($data['tmp_name'], $directory.$filename))
            return $this->erreur("Fichier '$nom': Erreur lors du transfert du fichier", $nom);


        // Transfert réussi
        chmod($directory.$filename, $chmod);
        return $filename; // true
    }


    //-- Matrice de test ----------------------------------------------------------------------

    // But: Au lieu de définir les test* les uns à la suite des autres dans le code
    //		celles-ci, seront définit dans un tableau Array pour simplifier l'écriture et la
    //		configuration
    public function testMatrice($form_data, $matrice)
    {
        if(!is_array($matrice) || empty($matrice))
            $this->erreur('Matrice non init', 'matrice');

        elseif(!is_array($form_data) || empty($form_data))
            $this->erreur('Aucune donnée envoyé par le formulaire', 'matrice');

        /* Format matrice:
            array
            (
                'var_key' => array
                (
                    'lib' 			=> 'Nom form',
                    'test'			=> 'testVide'
                    'arg'			=> array(arg3, arg4, arg5, etc), // Optionnel
                    'msg_erreur'	=>	'Lorem ipsu dolor'	// Optionnel, message erreur personnalisé
                )
            );
        */
        foreach($matrice as $key => $cfg)
        {
            if(empty($cfg['test']) || !method_exists($this, $cfg['test']))
            {
                $this->erreur("Matrice, test '{$cfg['test']}' invalide", $key);
                continue;
            }
            elseif(!isset($form_data[$key]))
                $form_data[$key] = null;


            // Arguments
            $args = array(&$cfg['lib'], &$form_data[$key]);
            if(!empty($cfg['arg']) && is_array($cfg['arg']))
                $args = array_merge($args, $cfg['arg']);

            // Lancer le test
            if(!call_user_func_array(array($this, $cfg['test']), $args))
            {
                // Personnaliser le message d'erreur si nécessaire
                if(!empty($cfg['msg_erreur']))
                    $this->erreur[$key] = $cfg['msg_erreur'];
            }
        }
    }



    //-- Fonction de callback (expérimental) --------------------------------------------------
    public function callback()
    {
        if($this->ob_start)
            return true;

        elseif(headers_sent())
            return user_error('Error, headers already send. Callback cannot be enable.');

        elseif($this->nb_erreur() > 0)
        {
            $this->ob_start = true;
            ob_start();
        }
        else return false;
    }

    public function callback_rapport($color = 'red')
    {
        if(!$this->ob_start || ob_get_length() == 0)
            return false;

        $content = ob_get_clean();
        foreach($this->erreur as $id => $erreur)
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
