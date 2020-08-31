<?php
use Jgauthi\Component\Checkform\FormErreur;

// In this example, the vendor folder is located in "example/"
require_once __DIR__.'/vendor/autoload.php';

// Donnée diverses formulaire
$liste = [
    'etudiant' => 'Etudiant',
    'salarie' => 'Salarié',
    'cadre' => 'Cadre',
    'autre' => 'autre',
];

// Test du formulaire
$success = null;
if ('POST' === $_SERVER['REQUEST_METHOD']) {
    $form_data = $_POST;

    // Init class + fonction erreur personnalisée
    $form = new FormErreur;

    if (isset($form_data['use_func'])) {
        // Fonction personnalisée pour gérer les erreurs en-dehors de la class
        // --> utile dans le cas de framework comme drupal
        $customErrorFunction = function ($msg, $id) {
            echo "<p>[$id] $msg</p>";
        };

        $form->set_function_error($customErrorFunction);
    }

    // Montrer cas d'utilisation de la matrice
    if (!empty($form_data['use_matrice'])) {
        $form->testMatrice($form_data, [
            'civilite' => ['lib' => 'Civilite', 'test' => 'testCivilite'],
            'nom' => ['lib' => 'Nom', 'test' => 'testVide'],
            'age' => ['lib' => 'Age', 'test' => 'testNum'],
            'tel' => ['lib' => 'Tél', 'test' => 'testTel'],
            'cp' => ['lib' => 'Code Postal', 'test' => 'testCp'],
            'mail' => ['lib' => 'Email', 'test' => 'testEmail'],
            'datebirth' => ['lib' => 'Date naissance', 'test' => 'testDate'],
            'statut' => [
                'lib' => 'Statut',
                'test' => 'testSelect',
                'arg' => [$liste],
                'msg_erreur' => 'Veuillez sélectionner un statut adéquate',
            ],
            //testLogin
            //testPassword
            //testUpload
        ]);

        // Fonction classique (sans matrice)
    } else {
        $form->testCivilite('Civilite', $form_data['civilite']);
        $form->testVide('Nom', $form_data['nom']);
        $form->testNum('Age', $form_data['age']);
        $form->testTel('Tél', $form_data['tel']);
        $form->testCp('Code Postal', $form_data['cp']);
        $form->testEmail('Email', $form_data['mail']);
        $form->testDate('Date naissance', $form_data['datebirth']);
        $form->testSelect('Statut', $form_data['statut'], $liste);
    }

    $success = false;
    if ($form->nb_erreur() === 0) {
        $success = true;
        // [...] Save data to DB, etc.
    }
}

// Pré-remplir le formulaire avec des données erronées
else {
    $form_data = [
        'civilite' => 'M',
        'nom' => null,
        'age' => 'Z',
        'tel' => '05 00 11 22 33 44 55',
        'cp' => null,
        'mail' => 'testé@example.com',
        'datebirth' => '32-02-1980',
        'statut' => 'etudiant',
        'use_matrice' => true,
        'use_func' => false,
    ];
}

function input(string $type, string $name, ?string $default_value = null): string
{
    global $form_data;
    $input = ' type="'.$type.'" name = "'.$name.'" ';

    if ('radio' === $type || 'checkbox' === $type) {
        $input .= 'id="'.$name.'_'.$default_value.'" value="'.addslashes($default_value).'" ';

        if (!empty($form_data[$name]) && $form_data[$name] === $default_value) {
            $input .= 'checked="checked" ';
        }
    } else {
        $input .= 'id="'.$name.'" value="'.addslashes($form_data[$name]).'" ';
    }

    return $input;
}

function select(string $name, iterable $liste): string
{
    global $form_data;
    $input = '<select name="'.$name.'" id="'.$name.'">'.
        '<option value="">-- Sélectionner une valeur --</a>';

    foreach ($liste as $val => $lib) {
        $input .= "\n\t".'<option value="'.addslashes($val).'"';
        if ($val === $form_data[$name]) {
            $input .= ' selected="selected"';
        }

        $input .= '>'.$lib.'</option>';
    }

    $input .= "\n</select>";

    return $input;
}

?>

<h1>Test class FormErreur</h1>
<form action="<?=basename($_SERVER['PHP_SELF'])?>" method="post">
    <fieldset>
        <legend>Test class FormErreur</legend>
        <p>
            Civilité:<br />
            <label><input<?=input('radio', 'civilite', 'M'); ?>/>&nbsp;M.</label>
            <label><input<?=input('radio', 'civilite', 'Mlle'); ?>/>&nbsp;Mlle.</label>
            <label><input<?=input('radio', 'civilite', 'Mme'); ?>/>&nbsp;Mme.</label>
        </p>

        <p>
            <label for="nom">Nom: <input<?=input('text', 'nom'); ?>/></label><br>
            <label for="age">Age: <input<?=input('text', 'age'); ?>/></label><br>
            <label for="tel">Tél: <input<?=input('text', 'tel'); ?>/></label><br>
            <label for="cp">Code postal: <input<?=input('text', 'cp'); ?>/></label><br>
            <label for="mail">Email: <input<?=input('text', 'mail'); ?>/></label><br>
            <label for="datebirth">Date naissance: <input<?=input('text', 'datebirth'); ?>/></label> (format: JJ/MM/AAAA)<br>
            <label for="statut">Statut: <?=select('statut', $liste); ?></label>
        </p>

        <p>
            <label>
                <input<?=input('checkbox', 'use_matrice', true); ?>/>&nbsp;Utiliser la matrice ?
            </label>
        </p>

        <p>
            <label>
                <input<?=input('checkbox', 'use_func', true); ?>/>&nbsp;Fonction erreur personnalisée ?
            </label>
        </p>


        <br />
        <input type="submit" value="Envoyer"  />
    </fieldset>
</form>

<?php if (null !== $success && !empty($form)): ?>
<fieldset>
    <legend>Statut formulaire: <?=(($success) ? 'succès' : 'ECHEC')?></legend>
    <p><?=$form->rapport(); ?></p>
    <fieldset>
<?php endif ?>

<?php var_dump($form_data); ?>
