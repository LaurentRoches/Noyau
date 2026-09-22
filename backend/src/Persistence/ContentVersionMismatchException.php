<?php

declare(strict_types=1);

namespace App\Persistence;

/**
 * Une run ne correspond plus au contenu servi (`04` §6.3, D-18).
 *
 * **Pourquoi c'est un conflit et non une erreur.** La requête est bien formée,
 * la run existe, le serveur fonctionne. Ce qui ne va plus, c'est l'accord entre
 * l'état du serveur et ce que cette run suppose — la définition même d'un 409.
 * D'où `LogicException`, que le `Router` mappe déjà ainsi.
 *
 * **Pourquoi une classe dédiée alors que `LogicException` suffirait au statut.**
 * Le 409 sert à tout conflit d'état : action hors séquence, achat sur une
 * boutique fermée. Ces conflits-là se corrigent en jouant autrement. Celui-ci
 * ne se corrige pas : aucune suite d'actions ne rendra à cette run le catalogue
 * sous lequel elle a commencé. Le client doit pouvoir faire la différence sans
 * analyser une phrase en anglais, et c'est le `code` machine que le `Router`
 * attache à cette classe-ci qui le lui permet.
 *
 * **Pourquoi le message porte les deux empreintes.** Sans elles, le refus est
 * indiscernable d'un bug. Avec elles, la ligne suffit à trancher : base de
 * développement à jeter, ou catalogue modifié par erreur.
 */
final class ContentVersionMismatchException extends \LogicException
{
    public function __construct(string $runId, string $recordedVersion, string $currentVersion)
    {
        parent::__construct(sprintf(
            'Run "%s" was created under content version %s, but the current content is %s. '
            . 'Its action log rebuilds the game from the catalogs, so replaying it against '
            . 'changed content would produce a different game from the one that was played. '
            . 'It is refused rather than served. The database is disposable until J1 (D-18): '
            . 'delete the SQLite file and start a new run.',
            $runId,
            $recordedVersion,
            $currentVersion,
        ));
    }
}
