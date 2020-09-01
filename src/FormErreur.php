<?php
/*******************************************************************************
  * @name: FormErreur
  * @note: Form error handler
  * @author: Jgauthi <github.com/jgauthi>, created at [5mars2007]
  * @version: 2.1
  * @todo:
    - Ajout gestion des traductions

*******************************************************************************/

namespace Jgauthi\Component\Checkform;

use PDO;
use PDOStatement;

class FormErreur
{
    protected const LIST_CIVILITY = [ 'M', 'Mme', 'Mlle' ];
    protected const UPLOAD_DEFAULT_CHMOD = 0664;

    protected array $erreur = [];
    protected bool $ob_start = false;

    /** @var callable|null */
    protected $function_erreur = null;

    public function __construct()
    {
        // A défaut d'une fonction de traduction, utiliser le gestionnaire d'erreur classique
        //$this->set_trad_function(array($this, 'erreur'));

        // Définir une fonction d'erreur personnalisé
        $this->set_function_error([$this, 'erreur_default_function']);
    }

    public function set_function_error(callable $function): void
    {
        $this->function_erreur = $function;
    }

    //-- Gestion des erreurs ------------------------------------------------------------------

    public function erreur(string $message, $id = null): bool
    {
        if (empty($id)) {
            $this->erreur[] = $message;
        } else {
            $this->erreur[$id] = $message;
        }

        call_user_func_array($this->function_erreur, [$message, $id]);
        return false;
    }

    public function erreur_default_function(string $message, string $id): bool
    {
        return false;
    }

    public function nb_erreur(): int
    {
        return count($this->erreur);
    }

    public function rapport(string $export_format = 'html'): ?string
    {
        if (0 === $this->nb_erreur()) {
            return null;
        }

        $rapport = "Les erreurs suivantes ont été détectées: \n";
        $this->erreur = array_unique($this->erreur); // Supprimer les doublons

        foreach ($this->erreur as $erreur) {
            $rapport .= "* $erreur\n";
        }

        switch ($export_format) {
            case 'html':
                return nl2br(htmlentities($rapport, ENT_QUOTES, 'UTF-8'));

            case 'alert':
                return "window.alert('".
                    str_replace(["\r\n", "\r", "\n"], '\n', addslashes($rapport)).
                    "');";

            case 'javascript':
                return "<script type=\"text/javascript\">\nwindow.alert('".
                    str_replace(["\r\n", "\r", "\n"], '\n', addslashes($rapport)).
                    "');\n</script>";

            default: // Texte brute
                return $rapport;
        }
    }

    //-- Fonction de gestion ------------------------------------------------------------------------------------

    public function testVide(string $nom, &$value): bool
    {
        if (!isset($value) || null === $value || '' === trim($value)) {
            return $this->erreur("Le champ '{$nom}' est vide", $nom);
        }

        return true;
    }

    public function testNum(string $nom, &$value): bool
    {
        if (!isset($value) || !is_numeric($value)) {
            return $this->erreur("Le champ '{$nom}' n'est pas un nombre", $nom);
        }

        return true;
    }

    public function testLogin($value, PDO $pdo, ?string $table = null, string $login_champ = 'login', ?int $id_compte = null, string $id_champ = 'id'): bool
    {
        if (!$this->testVide('Login', $value)) {
            return false;
        }

        // Nombre de caractère
        if (mb_strlen($value) < 4 || mb_strlen($value) > 50) {
            return $this->erreur(
                'Votre login doit-être constitué d\'au moins 4 caractères et de 50 caractères au maximun',
                $login_champ
            );

            // Vérifier que le login existe déjà
        } elseif (!empty($table) && !empty($login_champ)) {
            // Action créer par défaut
            $req = "SELECT `$login_champ` FROM `{$table}` WHERE `$login_champ` = :login";
            $search = ['login' => $value];

            // Action modifier
            if (!empty($id_compte) && !empty($id_champ)) {
                $req .= " AND `{$id_champ}` !=  :id";
                $search['id'] = $id_compte;
            }

            /** @var PDOStatement $stmt */
            $stmt = $pdo->prepare($req)->execute($search);
            if ($stmt->rowCount() > 0) {
                return $this->erreur(
                    "Le login \"{$value}\" est déjà réservé, choississez-en un autre",
                    'Login'
                );
            }
        }

        return true;
    }

    public function testDate(string $nom, $value): bool
    {
        $regexp = '#^([0-9]{2})\/([0-9]{2})\/([0-9]{4})$#i';
        if (!preg_match($regexp, $value, $reg)) {
            return $this->erreur("Le format de la date '{$nom}' est incorrecte. Respecter le format: JJ/MM/YYYY", $nom);
        } elseif (!checkdate((int) $reg[2], (int) $reg[1], $reg[3])) {
            return $this->erreur("La date '{$nom}' fourni n'existe pas", $nom);
        }

        return true;
    }

    public function testSelect(string $nom, $value, array $select, $multi_select = false): bool
    {
        // Pas de multi-select, 1 seul option sélectionné
        if (!$multi_select) {
            if (!$this->testVide($nom, $value) || !array_key_exists($value, $select)) {
                return $this->erreur("Sélectionner votre {$nom}", $nom);
            }

            return true;
        }

        if (!is_array($value) || 0 === count($value)) {
            return $this->erreur("Sélectionner votre {$nom}", $nom);
        }

        // Vérifier que les valeurs récupérées correspondent au select
        foreach ($value as $search) {
            if (!$this->testVide($nom, $value) || !array_key_exists($nom, $search)) {
                return $this->erreur("Sélectionner votre {$nom}", $nom);
            }
        }

        // Si $multi_select est un chiffre,
        // --> vérifier que le nombre d'objets sélectionnée correspond à $multi_select
        if (true !== $multi_select && is_numeric($multi_select) && $multi_select !== count($value)) {
            return $this->erreur("Le nombre de {$nom} sélectionné n'est pas correcte", $nom);
        }

        return true;
    }

    public function testCivilite(string $nom, $value, array $requiredValues = self::LIST_CIVILITY): bool
    {
        if (!in_array($value, $requiredValues)) {
            return $this->erreur(
                "Le choix de '{$nom}' n'est pas correcte, choisissez ". implode(', ', $requiredValues),
                $nom
            );
        }

        return true;
    }

    public function testTel(string $nom, $value): bool
    {
        // [ATTENTION] Ne gère pas les numéros de téléphone étranger
        $regexp = '#^0[1-9][ .-]?([0-9]{2}[ .-]?){4}$#';

        if (!preg_match($regexp, $value)) {
            return $this->erreur(
                "Le format du numéro de '{$nom}' est incorrecte: ".
                'respecter le format: 00 00 00 00 00 ou 0000000000',
                $nom
            );
        }

        return true;
    }

    // Fonction pas encore développé pour l'instant
    public function testCp(string $nom, $value): bool
    {
        return $this->testVide($nom, $value);
    }

    public function testEmail(string $nom, $value): bool
    {
        if (!$this->testVide($nom, $value)) {
            return false;
        } elseif (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $this->erreur("le champ '{$nom}' est incorrecte, respecter le format: nom@serveur.domaine", $nom);
        }

        return true;
    }

    public function testPassword(string $pass1, string $pass2): bool
    {
        if (!$this->testVide('Mot de passe', $pass1)) {
            return false;
        } elseif (!$this->testVide('Mot de passe confirmation', $pass2)) {
            return false;
        } elseif ($pass1 !== $pass2) {
            return $this->erreur('Le mot de passe et celui de confirmation sont différents', 'Mot de passe');
        }

        return true;
    }

    public function testUpload(string $nom, array $data, ?string $doctype = null, string $directory = null, ?string $filename = null, int $chmod = self::UPLOAD_DEFAULT_CHMOD): bool
    {
        // Fichier mal uploadé
        if (UPLOAD_ERR_OK !== $data['error'] || !is_uploaded_file($data['tmp_name'])) {
            // Chercher le bon message d'erreur
            switch ($data['error']) {
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

            return $this->erreur("Fichier '{$nom}': {$message}", $nom);
        }

        // Nom du fichier sur le serveur
        if (empty($filename)) {
            $filename = $data['name'];
        }

        if (!empty($doctype) && !preg_match("#$doctype#i", $data['type'])) {
            return $this->erreur("Fichier '{$nom}': type de fichier incorrect", $nom);
        } elseif (!move_uploaded_file($data['tmp_name'], $directory.$filename)) {
            return $this->erreur("Fichier '{$nom}': Erreur lors du transfert du fichier", $nom);
        }

        // Transfert réussi
        chmod($directory.$filename, $chmod);

        return $filename; // true
    }

    //-- Matrice de test ----------------------------------------------------------------------

    /**
     * But: Au lieu de définir les test* les uns à la suite des autres dans le code celles-ci, seront définit dans un tableau Array pour simplifier l'écriture et la configuration.
     */
    public function testMatrice(array $form_data, iterable $matrice): bool
    {
        if (empty($matrice)) {
            $this->erreur('Matrice non init', 'matrice');
        } elseif (empty($form_data)) {
            $this->erreur('Aucune donnée envoyé par le formulaire', 'matrice');
        }

        /* Format matrice:
            [
                'var_key' => [
                    'lib'           => 'Nom form',
                    'test'          => 'testVide'
                    'arg'           => array(arg3, arg4, arg5, etc), // Optionnel
                    'msg_erreur'    =>  'Lorem ipsu dolor'  // Optionnel, message erreur personnalisé
                ]
            ];
        */

        foreach ($matrice as $key => $cfg) {
            if (empty($cfg['test']) || !method_exists($this, $cfg['test'])) {
                $this->erreur("Matrice, test '{$cfg['test']}' invalide", $key);
                continue;
            } elseif (!isset($form_data[$key])) {
                $form_data[$key] = null;
            }

            // Arguments
            $args = [&$cfg['lib'], &$form_data[$key]];
            if (!empty($cfg['arg']) && is_array($cfg['arg'])) {
                $args = array_merge($args, $cfg['arg']);
            }

            // Lancer le test
            if (!call_user_func_array([$this, $cfg['test']], $args)) {
                // Personnaliser le message d'erreur si nécessaire
                if (!empty($cfg['msg_erreur'])) {
                    $this->erreur[$key] = $cfg['msg_erreur'];
                }
            }
        }

        return true;
    }

    //-- Fonction de callback (expérimental) --------------------------------------------------

    public function callback(): bool
    {
        if ($this->ob_start) {
            return true;
        } elseif (headers_sent()) {
            trigger_error('Error, headers already send. Callback cannot be enable.');
            return false;
        } elseif ($this->nb_erreur() > 0) {
            $this->ob_start = true;
            ob_start();
        }

        return false;
    }

    public function callback_rapport(string $color = 'red'): bool
    {
        if (!$this->ob_start || 0 === ob_get_length()) {
            return false;
        }

        $content = ob_get_clean();
        foreach ($this->erreur as $id => $erreur) {
            $id2 = htmlentities($id);
            $erreur = htmlentities(str_replace("'$id'", '', $erreur), ENT_QUOTES, 'UTF-8');
            $content = preg_replace(
                "#<label([^>]+)>($id|$id2)#i",
                "<label title=\"$erreur\" style=\"color: $color\"\\1>\\2",
                $content
            );
        }

        echo $content;
        return true;
    }
}