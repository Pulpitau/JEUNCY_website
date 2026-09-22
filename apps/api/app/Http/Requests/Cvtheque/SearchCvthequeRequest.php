<?php

namespace App\Http\Requests\Cvtheque;

use Illuminate\Foundation\Http\FormRequest;

class SearchCvthequeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'max:255'],
            // Pas de filtre 'city' : la ville n'est plus montree au recruteur
            // (regle d'exposition unique, MOBILE.md §4.3). La laisser en
            // filtre la revelerait par inference — taper une ville et compter
            // les resultats vaut affichage.
            'language' => ['sometimes', 'string', 'max:100'],
            // Remplace l'ancien filtre sur le texte libre driving_license :
            // c'est desormais un booleen structure (colonne has_driving_license).
            'has_driving_license' => ['sometimes', 'boolean'],
            // Bornes d'age. 16 est l'age minimum du modele match (MOBILE.md
            // §9), 99 une borne de bon sens contre une URL forgee. Le site
            // accepte toujours un compte a 15 ans ; il n'est simplement pas
            // proposable a un employeur.
            'age_min' => ['sometimes', 'integer', 'min:16', 'max:99'],
            'age_max' => [
                'sometimes', 'integer', 'min:16', 'max:99',
                // gte SEULEMENT si une borne basse est reellement fournie.
                // Sans cette condition, Laravel ne trouve pas le champ
                // age_min, compare age_max a la chaine « age_min », conclut
                // que les types different et refuse : une recherche
                // « jusqu'a 25 ans » — proposee telle quelle par le
                // formulaire de la CVtheque — repondait 400 avec un message
                // anglais incomprehensible pour le recruteur.
                ...($this->filled('age_min') ? ['gte:age_min'] : []),
            ],
            // Bornees a 10 : chaque entree ajoute un whereHas, donc une
            // sous-requete. Sans plafond, une URL forgee avec 500 competences
            // suffirait a faire ramer la base.
            'skills' => ['sometimes', 'array', 'max:10'],
            'skills.*' => ['string', 'max:100'],
            'software' => ['sometimes', 'array', 'max:10'],
            'software.*' => ['string', 'max:100'],
        ];
    }

    /**
     * Messages en francais : un recruteur qui se trompe de bornes d'age lit
     * la reponse de l'API telle quelle dans le bandeau d'erreur du site.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'age_min.min' => "L'âge minimum est de 16 ans.",
            'age_max.min' => "L'âge maximum est de 16 ans.",
            'age_max.gte' => "L'âge maximum doit être supérieur ou égal à l'âge minimum.",
        ];
    }
}
