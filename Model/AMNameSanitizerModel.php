<?php

namespace MauticPlugin\AMNameSanitizerBundle\Model;

use Mautic\CoreBundle\Model\FormModel;

class AMNameSanitizerModel extends FormModel
{
    /**
     * Get all names of the database together with the lead id and puts in an array.
     *
     * @return array
     */
    public function getNames()
    {

        $q = $this->em->getConnection()->createQueryBuilder()
            ->select('l.id, l.firstname, l.lastname')
            ->from(MAUTIC_TABLE_PREFIX . 'leads', 'l')
            ->where('!ISNULL(l.firstname) OR !ISNULL(l.lastname)');

        $results = $q->execute()->fetchAll();

        return $results;
    }

    private function mb_ucfirst($name, $encoding)
    {

        $size = mb_strlen($name, $encoding);
        $firstChar = mb_substr($name, 0, 1, $encoding);
        $then = mb_substr($name, 1, $size - 1, $encoding);

        return mb_strtoupper($firstChar, $encoding) . $then;
    }

    /**
     * Correct the case of the input name.
     *
     * @param string $name
     *
     * @return string
     */
    public function nameCase($name)
    {

        //Evita caracteres estranhos no nome
        $name = str_replace(str_split("'\/0123456789()*%$|~#?!^][}{;/\\<>,\""), '', $name);

        //Separadores e excessões de lower e uppercase
        $word_splitters = array(' ', '-', "O'", "L'", "D'", 'St.', 'Mc', 'Dr', 'Dra', 'Sr', 'Sra');
        $lowercase_exceptions = array('the', 'van', 'den', 'von', 'und', 'der', 'de', 'da', 'das', 'of', 'and', "l'", "d'", 'do', 'dos', 'no', 'nas', 'nos', 'ou', 'e');
        $uppercase_exceptions = array('I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X');

        $name = mb_strtolower($name, 'UTF-8');

        foreach ($word_splitters as $delimiter) {
            $words = explode($delimiter, $name);
            $newwords = array();
            foreach ($words as $word) {
                if (in_array(strtoupper($word), $uppercase_exceptions)) {
                    $word = strtoupper($word);
                } else
                if ( ! in_array($word, $lowercase_exceptions)) {
                    $word = self::mb_ucfirst($word, 'utf-8');
                }

                $newwords[] = $word;
            }
            if (in_array(strtolower($delimiter), $lowercase_exceptions)) {
                $delimiter = strtolower($delimiter);
            }

            $name = join($delimiter, $newwords);
        }

        return $name;
    }

    /**
     * Update the lead first and lastname.
     *
     * @param string $newFirstname
     * @param string $newLastname
     * @param int $leadId
     *
     * @return bool
     */
    public function updateName($lead)
    {
        if (is_object($lead)) {
            $leadTemp['firstname'] = $lead->firstname;
            $leadTemp['lastname'] = $lead->lastname;
            $leadId = $lead->id;
        } else {
            $leadTemp['firstname'] = $lead['firstname'];
            $leadTemp['lastname'] = $lead['lastname'];
            $leadId = $lead['id'];
        }
        $fullName = trim($leadTemp['firstname']) . ' ' . trim($leadTemp['lastname']);
        $newFullName = $this->nameCase($fullName);
        $firstName = $this->str_before($fullName, ' ');
        $lastName = $this->str_after($fullName, ' ');

        if ($firstName != $leadTemp['firstname'] || $lastName != $leadTemp['lastname']) {
            $q = $this->em->getConnection()->createQueryBuilder();
            $query = $q->update(MAUTIC_TABLE_PREFIX . 'leads', 'l')
                ->set('l.firstname', '"' . $firstName . '"')
                ->set('l.lastname', '"' . $lastName . '"')
                ->where("l.id = " . $leadId)
                ->execute();

            return true;
        }
        return false;
    }

    public function str_before($subject, $search)
    {
        return $search === '' ? $subject : explode($search, $subject)[0];
    }

    public function str_after($subject, $search)
    {
        return $search === '' ? $subject : explode($search, $subject)[1];
    }
}
