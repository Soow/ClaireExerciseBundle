<?php

namespace SimpleIT\ClaireExerciseBundle\Service\Exercise\ExerciseModel;

use Doctrine\Common\Collections\ArrayCollection;
use SimpleIT\ClaireExerciseBundle\Entity\DomainKnowledge\Knowledge;
use SimpleIT\ClaireExerciseBundle\Entity\ExerciseModel\ExerciseModel;
use SimpleIT\ClaireExerciseBundle\Entity\ExerciseModelFactory;
use SimpleIT\ClaireExerciseBundle\Entity\ExerciseResource\ExerciseResource;
use SimpleIT\ClaireExerciseBundle\Exception\InvalidModelException;
use SimpleIT\ClaireExerciseBundle\Exception\InvalidTypeException;
use SimpleIT\ClaireExerciseBundle\Exception\NoAuthorException;
use SimpleIT\ClaireExerciseBundle\Model\Resources\Exercise\Common\CommonExercise;
use SimpleIT\ClaireExerciseBundle\Model\Resources\ExerciseModel\Common\CommonModel;
use SimpleIT\ClaireExerciseBundle\Model\Resources\ExerciseModel\Common\ResourceBlock;
use
    SimpleIT\ClaireExerciseBundle\Model\Resources\ExerciseModel\GroupItems\ClassificationConstraints;
use SimpleIT\ClaireExerciseBundle\Model\Resources\ExerciseModel\GroupItems\Group;
use SimpleIT\ClaireExerciseBundle\Model\Resources\ExerciseModel\GroupItems\Model as GroupItems;
use
    SimpleIT\ClaireExerciseBundle\Model\Resources\ExerciseModel\GroupItems\ObjectBlock as GIObjectBlock;
use
    SimpleIT\ClaireExerciseBundle\Model\Resources\ExerciseModel\MultipleChoice\Model as MultipleChoice;
use
    SimpleIT\ClaireExerciseBundle\Model\Resources\ExerciseModel\MultipleChoice\QuestionBlock as MCQuestionBlock;
use
    SimpleIT\ClaireExerciseBundle\Model\Resources\ExerciseModel\OpenEndedQuestion\Model as OpenEnded;
use
    SimpleIT\ClaireExerciseBundle\Model\Resources\ExerciseModel\OpenEndedQuestion\QuestionBlock as OEQuestionBlock;
use SimpleIT\ClaireExerciseBundle\Model\Resources\ExerciseModel\OrderItems\Model as OrderItems;
use
    SimpleIT\ClaireExerciseBundle\Model\Resources\ExerciseModel\OrderItems\ObjectBlock as OIObjectBlock;
use SimpleIT\ClaireExerciseBundle\Model\Resources\ExerciseModel\PairItems\Model as PairItems;
use SimpleIT\ClaireExerciseBundle\Model\Resources\ExerciseModel\PairItems\PairBlock;
use SimpleIT\ClaireExerciseBundle\Model\Resources\ExerciseModelResource;
use SimpleIT\ClaireExerciseBundle\Model\Resources\ExerciseResource\CommonResource;
use SimpleIT\ClaireExerciseBundle\Model\Resources\ModelObject\MetadataConstraint;
use SimpleIT\ClaireExerciseBundle\Model\Resources\ModelObject\ObjectConstraints;
use SimpleIT\ClaireExerciseBundle\Model\Resources\ModelObject\ObjectId;
use SimpleIT\ClaireExerciseBundle\Repository\Exercise\ExerciseModel\ExerciseModelRepository;
use SimpleIT\ClaireExerciseBundle\Service\Exercise\DomainKnowledge\KnowledgeServiceInterface;
use
    SimpleIT\ClaireExerciseBundle\Service\Exercise\ExerciseResource\ExerciseResourceServiceInterface;
use SimpleIT\ClaireExerciseBundle\Service\Exercise\SharedEntity\SharedEntityService;
use SimpleIT\CoreBundle\Exception\NonExistingObjectException;
use SimpleIT\CoreBundle\Annotation\Transactional;

/**
 * Service which manages the exercise generation
 *
 * @author Baptiste Cablé <baptiste.cable@liris.cnrs.fr>
 */
class ExerciseModelService extends SharedEntityService implements ExerciseModelServiceInterface
{
    const ENTITY_TYPE = 'exerciseModel';

    /**
     * @var ExerciseModelRepository $exerciseModelRepository
     */
    protected $entityRepository;

    /**
     * @var ExerciseResourceServiceInterface
     */
    private $exerciseResourceService;

    /**
     * @var KnowledgeServiceInterface
     */
    private $knowledgeService;

    /**
     * Set exerciseResourceService
     *
     * @param ExerciseResourceServiceInterface $exerciseResourceService
     */
    public function setExerciseResourceService($exerciseResourceService)
    {
        $this->exerciseResourceService = $exerciseResourceService;
    }

    /**
     * Set knowledgeService
     *
     * @param \SimpleIT\ClaireExerciseBundle\Service\Exercise\DomainKnowledge\KnowledgeServiceInterface $knowledgeService
     */
    public function setKnowledgeService($knowledgeService)
    {
        $this->knowledgeService = $knowledgeService;
    }

    /**
     * Get an exercise Model (business object, no entity)
     *
     * @param int $exerciseModelId
     *
     * @return object
     * @throws \LogicException
     */
    public function getModel($exerciseModelId)
    {
        /** @var ExerciseModel $entity */
        $entity = $this->get($exerciseModelId);

        return $this->getModelFromEntity($entity);

    }

    /**
     * Get an exercise model from an entity
     *
     * @param ExerciseModel $entity
     *
     * @return CommonModel
     * @throws \LogicException
     */
    public function getModelFromEntity(ExerciseModel $entity)
    {
        // deserialize to get an object
        switch ($entity->getType()) {
            case CommonExercise::MULTIPLE_CHOICE:
                $class = ExerciseModelResource::MULTIPLE_CHOICE_MODEL_CLASS;
                break;
            case CommonExercise::GROUP_ITEMS:
                $class = ExerciseModelResource::GROUP_ITEMS_MODEL_CLASS;
                break;
            case CommonExercise::ORDER_ITEMS:
                $class = ExerciseModelResource::ORDER_ITEMS_MODEL_CLASS;
                break;
            case CommonExercise::PAIR_ITEMS:
                $class = ExerciseModelResource::PAIR_ITEMS_MODEL_CLASS;
                break;
            case CommonExercise::OPEN_ENDED_QUESTION:
                $class = ExerciseModelResource::OPEN_ENDED_QUESTION_CLASS;
                break;
            default:
                throw new \LogicException('Unknown type of model');
        }

        return $this->serializer->jmsDeserialize($entity->getContent(), $class, 'json');
    }

    /**
     * Create an entity from a resource
     *
     * @param ExerciseModelResource $modelResource
     *
     * @throws \SimpleIT\ClaireExerciseBundle\Exception\NoAuthorException
     * @return mixed
     */
    public function createFromResource($modelResource)
    {
        $modelResource->setComplete(
            $this->checkModelComplete(
                $modelResource->getType(),
                $modelResource->getParent(),
                $modelResource->getContent()
            )
        );

        $model = ExerciseModelFactory::createFromResource($modelResource);

        parent::fillFromResource($model, $modelResource);

        // required resources
        $reqResources = array();
        foreach ($modelResource->getRequiredExerciseResources() as $reqRes) {
            $reqResources[] = $this->exerciseResourceService->get($reqRes);
        }
        $model->setRequiredExerciseResources(new ArrayCollection($reqResources));

        // required resources
        $reqKnowledges = array();
        foreach ($modelResource->getRequiredKnowledges() as $reqKnowledge) {
            $reqKnowledges[] = $this->knowledgeService->get($reqKnowledge);
        }
        $model->setRequiredKnowledges(new ArrayCollection($reqKnowledges));

        return $model;
    }

    /**
     * Update an ExerciseResource object from a ResourceResource
     *
     * @param ExerciseModelResource $modelResource
     * @param ExerciseModel         $model
     *
     * @throws NoAuthorException
     * @return ExerciseModel
     */
    public function updateFromResource(
        $modelResource,
        $model
    )
    {
        parent::updateFromSharedResource($modelResource, $model, 'exercise_model_storage');

        if (!is_null($modelResource->getRequiredExerciseResources())) {
            $reqResources = array();
            foreach ($modelResource->getRequiredExerciseResources() as $reqRes) {
                $reqResources[] = $this->exerciseResourceService->get($reqRes);
            }
            $model->setRequiredExerciseResources(new ArrayCollection($reqResources));
        }

        if (!is_null($modelResource->getRequiredKnowledges())) {
            $reqKnowledges = array();
            foreach ($modelResource->getRequiredKnowledges() as $reqKnowledge) {
                $reqKnowledges[] = $this->knowledgeService->get($reqKnowledge);
            }
            $model->setRequiredKnowledges(new ArrayCollection($reqKnowledges));
        }
        if (!is_null($modelResource->getDraft())) {
            $model->setDraft($modelResource->getDraft());
        }

        if (!is_null($modelResource->getComplete())) {
            $model->setComplete($modelResource->getComplete());
        }

        $content = $modelResource->getContent();
        if (!is_null($content)) {
            $this->validateType($content, $model->getType());

            if ($model->getParent() === null) {
                $parentId = null;
            } else {
                $parentId = $model->getParent()->getId();
            }
            // Check if the model is complete with the new content
            $model->setComplete(
                $this->checkModelComplete(
                    $model->getType(),
                    $parentId,
                    $content
                )
            );
        }

        return $model;
    }

    /**
     * Add a requiredResource to an exercise model
     *
     * @param $exerciseModelId
     * @param $reqResId
     *
     * @return ExerciseModel
     */
    public function addRequiredResource(
        $exerciseModelId,
        $reqResId
    )
    {
        /** @var ExerciseResource $reqRes */
        $reqRes = $this->exerciseResourceService->get($reqResId);
        $this->entityRepository->addRequiredResource($exerciseModelId, $reqRes);

        return $this->get($exerciseModelId);
    }

    /**
     * Delete a required resource
     *
     * @param $exerciseModelId
     * @param $reqResId
     *
     * @return ExerciseModel
     */
    public function deleteRequiredResource(
        $exerciseModelId,
        $reqResId
    )
    {
        /** @var ExerciseResource $reqRes */
        $reqRes = $this->exerciseResourceService->get($reqResId);
        $this->entityRepository->deleteRequiredResource($exerciseModelId, $reqRes);
    }

    /**
     * Edit the required resources
     *
     * @param int             $exerciseModelId
     * @param ArrayCollection $requiredResources
     *
     * @return ExerciseModel
     */
    public function editRequiredResource(
        $exerciseModelId,
        ArrayCollection $requiredResources
    )
    {
        $exerciseModel = $this->entityRepository->find($exerciseModelId);

        $resourcesCollection = array();
        foreach ($requiredResources as $rr) {
            $resourcesCollection[] = $this->exerciseResourceService->get($rr);
        }
        $exerciseModel->setRequiredExerciseResources(new ArrayCollection($resourcesCollection));

        /** @var ExerciseModel $exerciseModel */
        $exerciseModel = $this->save($exerciseModel);

        return $exerciseModel->getRequiredExerciseResources();
    }

    /**
     * Add a required knowledge to an exercise model
     *
     * @param $exerciseModelId
     * @param $reqKnoId
     *
     * @return ExerciseModel
     */
    public function addRequiredKnowledge(
        $exerciseModelId,
        $reqKnoId
    )
    {
        /** @var Knowledge $reqKno */
        $reqKno = $this->knowledgeService->get($reqKnoId);
        $this->entityRepository->addRequiredKnowledge($exerciseModelId, $reqKno);

        return $this->get($exerciseModelId);
    }

    /**
     * Delete a required knowledge
     *
     * @param $exerciseModelId
     * @param $reqKnoId
     *
     * @return ExerciseModel
     */
    public function deleteRequiredKnowledge(
        $exerciseModelId,
        $reqKnoId
    )
    {
        /** @var Knowledge $reqKno */
        $reqKno = $this->knowledgeService->get($reqKnoId);
        $this->entityRepository->deleteRequiredKnowledge($exerciseModelId, $reqKno);
    }

    /**
     * Edit the required knowledges
     *
     * @param int             $exerciseModelId
     * @param ArrayCollection $requiredKnowledges
     *
     * @return ExerciseModel
     */
    public function editRequiredKnowledges(
        $exerciseModelId,
        ArrayCollection $requiredKnowledges
    )
    {
        $exerciseModel = $this->entityRepository->find($exerciseModelId);

        $reqKnowledgeCollection = array();
        foreach ($requiredKnowledges as $rk) {
            $reqKnowledgeCollection[] = $this->knowledgeService->get($rk);
        }
        $exerciseModel->setRequiredKnowledges(new ArrayCollection($reqKnowledgeCollection));

        /** @var ExerciseModel $exerciseModel */
        $exerciseModel = $this->save($exerciseModel);

        return $exerciseModel->getRequiredExerciseResources();
    }

    /**
     * Check if the content of an exercise model is sufficient to generate exercises.
     *
     * @param string      $type
     * @param int         $parentModelId
     * @param CommonModel $content
     *
     * @throws \LogicException
     * @throws \SimpleIT\ClaireExerciseBundle\Exception\InvalidModelException
     * @return boolean True if the model is complete
     */
    private function checkModelComplete(
        $type,
        $parentModelId,
        $content
    )
    {
        if ($parentModelId === null) {
            switch ($type) {
                case CommonModel::MULTIPLE_CHOICE:
                    /** @var MultipleChoice $content */
                    return $this->checkMCComplete($content);
                    break;
                case CommonModel::PAIR_ITEMS:
                    /** @var PairItems $content */
                    return $this->checkPIComplete($content);
                    break;
                case CommonModel::GROUP_ITEMS:
                    /** @var GroupItems $content */
                    return $this->checkGIComplete($content);
                    break;
                case CommonModel::ORDER_ITEMS:
                    /** @var OrderItems $content */
                    return $this->checkOIComplete($content);
                    break;
                case CommonModel::OPEN_ENDED_QUESTION:
                    /** @var OpenEnded $content */
                    return $this->checkOEQComplete($content);
                    break;
                default:
                    throw new \LogicException('Invalid type');
            }
        } else {
            if ($content !== null) {
                throw new InvalidModelException('A model must be a pointer OR have a content');
            }
            try {

                $parentModel = $this->get($parentModelId);
            } catch (NonExistingObjectException $neoe) {
                throw new InvalidModelException('The parent model cannot be found.');
            }

            return $parentModel->getPublic();
        }
    }

    /**
     * Check if a multiple choice content is complete
     *
     * @param MultipleChoice $content
     *
     * @return boolean
     */
    private function checkMCComplete(
        MultipleChoice $content
    )
    {
        if (is_null($content->isShuffleQuestionsOrder())) {
            return false;
        }
        $questionBlocks = $content->getQuestionBlocks();
        if (!count($questionBlocks) > 0) {
            return false;
        }
        /** @var MCQuestionBlock $questionBlock */
        foreach ($questionBlocks as $questionBlock) {
            if (!($questionBlock->getMaxNumberOfPropositions() >= 0
                && $questionBlock->getMaxNumberOfRightPropositions() >= 0)
            ) {
                return false;
            }

            if (!$this->checkBlockComplete(
                $questionBlock,
                array(CommonResource::MULTIPLE_CHOICE_QUESTION)
            )
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a pair items content is complete
     *
     * @param PairItems $content
     *
     * @return bool
     */
    private function checkPIComplete(
        PairItems $content
    )
    {
        $pairBlocks = $content->getPairBlocks();
        if (!count($pairBlocks) > 0) {
            return false;
        }

        /** @var PairBlock $pairBlock */
        foreach ($pairBlocks as $pairBlock) {
            if ($pairBlock->getPairMetaKey() == null) {
                return false;
            }

            if (!$this->checkBlockComplete(
                $pairBlock,
                array(
                    CommonResource::PICTURE,
                    CommonResource::TEXT
                )
            )
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a group items model is complete
     *
     * @param GroupItems $content
     *
     * @return bool
     */
    private function checkGIComplete(
        GroupItems $content
    )
    {
        if ($content->getDisplayGroupNames() != GroupItems::ASK
            && $content->getDisplayGroupNames() != GroupItems::HIDE
            && $content->getDisplayGroupNames() != GroupItems::SHOW
        ) {
            return false;
        }

        $globalClassification = false;
        if ($content->getClassifConstr() != null) {
            if (!$this->checkClassifConstr($content->getClassifConstr())) {
                return false;
            }
            $globalClassification = true;
        }

        $objectBlocks = $content->getObjectBlocks();
        if (!count($objectBlocks) > 0) {
            return false;
        }

        /** @var GIObjectBlock $objectBlock */
        foreach ($objectBlocks as $objectBlock) {
            if (!$globalClassification &&
                (
                    $objectBlock->getClassifConstr() == null
                    || !$this->checkClassifConstr($objectBlock->getClassifConstr())
                )
            ) {
                return false;
            }

            if (!$this->checkBlockComplete(
                $objectBlock,
                array(
                    CommonResource::TEXT,
                    CommonResource::PICTURE
                )
            )
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if an order items model is complete
     *
     * @param OrderItems $content
     *
     * @return bool
     */
    private function checkOIComplete(
        OrderItems $content
    )
    {
        if ($content->isGiveFirst() === null || $content->isGiveLast() === null) {
            return false;
        }

        $sequenceBlock = $content->getSequenceBlock();
        $objectBlocks = $content->getObjectBlocks();
        // both cannot be empty or filled
        if (empty($sequenceBlock) == empty($objectBlocks)) {
            return false;
        }

        if ($sequenceBlock !== null) {
            if ($sequenceBlock->isKeepAll() === null) {
                return false;
            }

            if (!$sequenceBlock->isKeepAll() &&
                ($sequenceBlock->isUseFirst() === null || $sequenceBlock->isUseLast() === null)
            ) {
                return false;
            }

            if (!$this->checkBlockComplete($sequenceBlock, array(CommonResource::SEQUENCE))) {
                return false;
            }
        } else {
            if ($content->getOrder() != OrderItems::ASCENDENT
                && $content->getOrder() != OrderItems::DESCENDENT
            ) {
                return false;
            }

            if (is_null($content->getShowValues())) {
                return false;
            }

            /** @var OIObjectBlock $objectBlock */
            foreach ($objectBlocks as $objectBlock) {
                if ($objectBlock->getMetaKey() === null) {
                    return false;
                }

                if (
                !$this->checkBlockComplete(
                    $objectBlock,
                    array(
                        CommonResource::PICTURE,
                        CommonResource::TEXT
                    )
                )
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if an open ended question model is complete
     *
     * @param OpenEnded $content
     *
     * @return bool
     */
    private function checkOEQComplete(
        OpenEnded $content
    )
    {
        if (is_null($content->isShuffleQuestionsOrder())) {
            return false;
        }
        $questionBlocks = $content->getQuestionBlocks();
        if (!count($questionBlocks) > 0) {
            return false;
        }

        /** @var OEQuestionBlock $questionBlock */
        foreach ($questionBlocks as $questionBlock) {
            if (!$this->checkBlockComplete(
                $questionBlock,
                array(CommonResource::OPEN_ENDED_QUESTION)
            )
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if a resource block is complete
     *
     * @param ResourceBlock $block
     * @param array         $resourceTypes
     *
     * @return boolean
     */
    private function checkBlockComplete(
        ResourceBlock $block,
        array $resourceTypes
    )
    {
        if (!($block->getNumberOfOccurrences() >= 0)) {
            return false;
        }

        if (count($block->getResources()) == 0 && $block->getResourceConstraint() === null) {
            return false;
        }

        /** @var ObjectId $resource */
        foreach ($block->getResources() as $resource) {
            if (!$this->checkObjectId($resource, $resourceTypes)) {
                return false;
            }
        }

        if ($block->getResourceConstraint() !== null
            && !$this->checkConstraintsComplete($block->getResourceConstraint(), $resourceTypes)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Check if and object constraints object is complete
     *
     * @param ObjectConstraints $resourceConstraints
     * @param array             $resourceTypes
     *
     * @return boolean
     */
    private function checkConstraintsComplete(
        ObjectConstraints $resourceConstraints,
        array $resourceTypes = array()
    )
    {
        if (!empty($resourceTypes) && !is_null($resourceConstraints->getType()) &&
            !in_array($resourceConstraints->getType(), $resourceTypes)
        ) {
            return false;
        }
        if (count($resourceConstraints->getMetadataConstraints()) == 0) {
            return false;
        }

        /** @var MetadataConstraint $mdc */
        foreach ($resourceConstraints->getMetadataConstraints() as $mdc) {
            if (!$this->checkMetadataConstraintComplete($mdc)) {
                return false;
            }
        }

        /** @var ObjectId $excluded */
        foreach ($resourceConstraints->getExcluded() as $excluded) {
            if (!$this->checkObjectId($excluded)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if an obect Id is valid (and exists)
     *
     * @param ObjectId $objectId
     * @param array    $resourceTypes
     *
     * @return bool
     */
    private function checkObjectId(
        ObjectId $objectId,
        array $resourceTypes = array()
    )
    {
        if (is_null($objectId->getId())) {
            return false;
        }
        try {
            $resource = $this->exerciseResourceService->get($objectId->getId());
        } catch (NonExistingObjectException $neoe) {
            return false;
        }

        if (!empty($resourceTypes) && !in_array($resource->getType(), $resourceTypes)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a metadata constraint is complete
     *
     * @param MetadataConstraint $mdc
     *
     * @return bool
     */
    private function checkMetadataConstraintComplete(
        MetadataConstraint $mdc
    )
    {
        if ($mdc->getKey() == null || $mdc->getComparator() == null) {
            return false;
        }

        return true;
    }

    /**
     * Check if a classification constraint is complete
     *
     * @param ClassificationConstraints $classifConstr
     *
     * @return bool
     */
    private function checkClassifConstr(
        ClassificationConstraints $classifConstr
    )
    {
        if ($classifConstr->getOther() != ClassificationConstraints::MISC
            && $classifConstr->getOther() != ClassificationConstraints::OWN
            && $classifConstr->getOther() != ClassificationConstraints::REJECT
        ) {
            return false;
        }

        if (count($classifConstr->getMetaKeys()) == 0) {
            return false;
        }

        /** @var Group $group */
        foreach ($classifConstr->getGroups() as $group) {
            $name = $group->getName();
            if (empty($name)) {
                return false;
            }

            if (count($group->getMDConstraints()) == 0) {
                return false;
            }

            /** @var MetadataConstraint $mdc */
            foreach ($group->getMDConstraints() as $mdc) {
                if (!$this->checkMetadataConstraintComplete($mdc)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Throws an exception if the content does not match the type
     *
     * @param $content
     * @param $type
     *
     * @throws \SimpleIT\ClaireExerciseBundle\Exception\InvalidTypeException
     */
    private function validateType(
        $content,
        $type
    )
    {
        if (($type === CommonModel::MULTIPLE_CHOICE &&
                get_class($content) !== ExerciseModelResource::MULTIPLE_CHOICE_MODEL_CLASS)
            || ($type === CommonModel::GROUP_ITEMS
                && get_class($content) !== ExerciseModelResource::GROUP_ITEMS_MODEL_CLASS)
            || ($type === CommonModel::ORDER_ITEMS &&
                get_class($content) !== ExerciseModelResource::ORDER_ITEMS_MODEL_CLASS)
            || ($type === CommonModel::PAIR_ITEMS &&
                get_class($content) !== ExerciseModelResource::PAIR_ITEMS_MODEL_CLASS)
            || ($type === CommonModel::OPEN_ENDED_QUESTION &&
                get_class($content) !== ExerciseModelResource::OPEN_ENDED_QUESTION_CLASS)
        ) {
            throw new InvalidTypeException('Content does not match exercise model type');
        }
    }
}