<?php

namespace SimpleIT\ClaireExerciseBundle\Entity;

use SimpleIT\ClaireExerciseBundle\Entity\CreatedExercise\StoredExercise;
use SimpleIT\ClaireExerciseBundle\Entity\ExerciseModel\OwnerExerciseModel;

/**
 * Class to manage the creation of StoredExercise
 *
 * @author Baptiste Cablé <baptiste.cable@liris.cnrs.fr>
 */
abstract class StoredExerciseFactory
{
    /**
     * Create a new StoredExercise object
     *
     * @param string             $content Content
     * @param OwnerExerciseModel $ownerExerciseModel
     *
     * @return StoredExercise
     */
    public static function create($content, OwnerExerciseModel $ownerExerciseModel)
    {
        $storedExercise = new StoredExercise();
        $storedExercise->setContent($content);
        $storedExercise->setOwnerExerciseModel($ownerExerciseModel);

        return $storedExercise;
    }
}