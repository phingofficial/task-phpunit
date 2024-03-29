<?php

/**
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information please see
 * <http://phing.info>.
 */

namespace Phing\Task\Ext\PhpUnit;

use Exception;
use Phing\Project;
use Phing\Type\Element\ClasspathAware;
use Phing\Type\Element\FileSetAware;
use ReflectionException;
use ReflectionClass;

/**
 * Scans a list of files given by the fileset attribute, extracts valid test cases
 *
 * @author Michiel Rook <mrook@php.net>
 *
 * @package phing.tasks.ext.phpunit
 *
 * @since 2.1.0
 */
class BatchTest
{
    use ClasspathAware;
    use FileSetAware;

    /**
     * the reference to the project
     */
    private $project = null;

    /**
     * names of classes to exclude
     */
    private $excludeClasses = [];

    /**
     * name of the batchtest/suite
     */
    protected $name = "Phing Batchtest";

    /**
     * Create a new batchtest instance
     *
     * @param Project $project the project it depends on.
     */
    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    /**
     * Sets the name of the batchtest/suite
     *
     * @param $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Sets the classes to exclude.
     *
     * @param string $exclude
     *
     * @return void
     */
    public function setExclude($exclude)
    {
        $this->excludeClasses = explode(" ", $exclude);
    }

    /**
     * Iterate over all filesets and return the filename of all files.
     *
     * @return array an array of filenames
     */
    private function getFilenames()
    {
        $filenames = [];

        foreach ($this->filesets as $fileset) {
            $ds = $fileset->getDirectoryScanner($this->project);
            $ds->scan();

            $files = $ds->getIncludedFiles();

            foreach ($files as $file) {
                $filenames[] = $ds->getBaseDir() . "/" . $file;
            }
        }

        return $filenames;
    }

    /**
     * Checks wheter $input is a PHPUnit Test.
     *
     * @param $input
     *
     * @return bool
     */
    private function isTestCase($input)
    {
        return is_subclass_of($input, '\PHPUnit\Framework\TestCase') ||
            is_subclass_of($input, '\PHPUnit\Framework\TestSuite');
    }

    /**
     * Filters an array of classes, removes all classes that are not test cases or test suites,
     * or classes that are declared abstract.
     *
     * @param object $input
     *
     * @return bool
     * @throws ReflectionException
     */
    private function filterTests($input)
    {
        $reflect = new ReflectionClass($input);

        return $this->isTestCase($input) && (!$reflect->isAbstract());
    }

    /**
     * Returns an array of test cases and test suites that are declared
     * by the files included by the filesets
     *
     * @return array an array of tests.
     * @throws Exception
     */
    public function elements()
    {
        $filenames = $this->getFilenames();

        $declaredClasses = [];

        foreach ($filenames as $filename) {
            $definedClasses = PHPUnitUtil::getDefinedClasses($filename, $this->classpath);

            foreach ($definedClasses as $definedClass) {
                $this->project->log("(PHPUnit) Adding $definedClass (from $filename) to tests.", Project::MSG_DEBUG);
            }

            $declaredClasses = array_merge($declaredClasses, $definedClasses);
        }

        $elements = array_filter($declaredClasses, [$this, "filterTests"]);

        return $elements;
    }
}
