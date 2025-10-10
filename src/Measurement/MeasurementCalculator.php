<?php

namespace App\Measurement;

use App\Measurement\Data\MeasurementCapture;
use App\Measurement\Data\MeasurementSession;
use App\Measurement\Support\VectorMath;

class MeasurementCalculator
{
    private MeasurementSession $session;

    public function __construct(MeasurementSession $session)
    {
        $this->session = $session;
    }

    /**
     * @return array<string, mixed>
     */
    public function calculate(): array
    {
        $neutral = $this->session->getCapture('neutral');
        $reading = $this->session->getCapture('reading');
        $leftPose = $this->session->getCapture('left30');
        $rightPose = $this->session->getCapture('right30');

        $metrics = [
            'pupillary_distance' => $this->computePupillaryDistance($neutral),
            'fitting_height' => $this->computeFittingHeight($neutral, $reading),
            'vertex_distance' => $this->computeVertexDistance($neutral),
            'pantoscopic_tilt' => $this->computePantoscopicTilt($neutral),
            'frame_wrap' => $this->computeFrameWrap($leftPose, $rightPose),
            'frame_size' => $this->computeFrameSize($neutral),
            'posture' => $this->computePosture($neutral, $reading),
        ];

        return $metrics;
    }

    /**
     * @return array<string, float>
     */
    private function computePupillaryDistance(MeasurementCapture $capture): array
    {
        $pupils = $capture->getPupilCenters();
        $nose = $capture->getNoseBridgePoint();
        $geometry = $capture->getFrameGeometry();

        $horizontal = $geometry->getHorizontalAxis();

        $leftVector = VectorMath::subtract($pupils['left'], $nose);
        $rightVector = VectorMath::subtract($pupils['right'], $nose);

        $leftProjection = VectorMath::projectVector($leftVector, $horizontal);
        $rightProjection = VectorMath::projectVector($rightVector, $horizontal);

        $leftDistance = VectorMath::magnitude($leftProjection);
        $rightDistance = VectorMath::magnitude($rightProjection);
        $binocular = VectorMath::magnitude(VectorMath::subtract($pupils['left'], $pupils['right']));

        return [
            'left' => $leftDistance,
            'right' => $rightDistance,
            'binocular' => $binocular,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function computeFittingHeight(MeasurementCapture $neutral, MeasurementCapture $reading): array
    {
        $geometry = $neutral->getFrameGeometry();
        $vertical = $geometry->getVerticalAxis();

        $neutralHeights = $this->distanceToLowerRim($neutral, $vertical);
        $readingHeights = $this->distanceToLowerRim($reading, $vertical);

        return [
            'primary_gaze_left' => $neutralHeights['left'],
            'primary_gaze_right' => $neutralHeights['right'],
            'reading_gaze_left' => $readingHeights['left'],
            'reading_gaze_right' => $readingHeights['right'],
        ];
    }

    /**
     * @param array{0: float, 1: float, 2: float} $axis
     * @return array<string, float>
     */
    private function distanceToLowerRim(MeasurementCapture $capture, array $axis): array
    {
        $pupils = $capture->getPupilCenters();
        $lowerRim = $capture->getLowerRimPoints();

        $results = [];
        foreach (['left', 'right'] as $eye) {
            $vector = VectorMath::subtract($pupils[$eye], $lowerRim[$eye]);
            $projection = VectorMath::projectVector($vector, $axis);
            $results[$eye] = VectorMath::magnitude($projection);
        }

        return $results;
    }

    /**
     * @return array<string, float>
     */
    private function computeVertexDistance(MeasurementCapture $capture): array
    {
        $geometry = $capture->getFrameGeometry();
        $normal = $geometry->getNormal();
        $origin = $geometry->getOrigin();
        $pupils = $capture->getPupilCenters();
        $corneas = $capture->getCornealApexes();
        $lensSamples = $capture->getLensInnerSamples();

        $lensPlanePoint = $lensSamples['center'];

        $results = [];
        foreach (['left', 'right'] as $eye) {
            $cornea = $corneas[$eye];
            $distance = VectorMath::signedDistanceToPlane($cornea, $lensPlanePoint, $normal);
            $results[$eye] = abs($distance);
        }

        $results['symmetry_delta'] = abs($results['left'] - $results['right']);
        $results['pupil_plane_offset'] = VectorMath::signedDistanceToPlane($pupils['left'], $origin, $normal);

        return $results;
    }

    /**
     * @return array<string, float>
     */
    private function computePantoscopicTilt(MeasurementCapture $capture): array
    {
        $geometry = $capture->getFrameGeometry();
        $headPose = $capture->getHeadPoseVectors();

        $frameNormal = $geometry->getNormal();
        $headVertical = $headPose['up'];

        $angle = VectorMath::angleBetween($frameNormal, $headVertical);

        return [
            'angle_radians' => $angle,
            'angle_degrees' => rad2deg($angle),
        ];
    }

    /**
     * @return array<string, float>
     */
    private function computeFrameWrap(MeasurementCapture $leftPose, MeasurementCapture $rightPose): array
    {
        $leftNormal = $leftPose->getFrameGeometry()->getNormal();
        $rightNormal = $rightPose->getFrameGeometry()->getNormal();

        $angle = VectorMath::angleBetween($leftNormal, $rightNormal);

        return [
            'angle_radians' => $angle,
            'angle_degrees' => rad2deg($angle),
        ];
    }

    /**
     * @return array<string, float>
     */
    private function computeFrameSize(MeasurementCapture $capture): array
    {
        $lowerRim = $capture->getLowerRimPoints();
        $lensSamples = $capture->getLensInnerSamples();
        $geometry = $capture->getFrameGeometry();

        $verticalAxis = $geometry->getVerticalAxis();
        $horizontalAxis = $geometry->getHorizontalAxis();

        $lensTop = $lensSamples['top'];
        $lensBottom = $lensSamples['bottom'];
        $lensTemporal = $lensSamples['temporal'];
        $lensNasal = $lensSamples['nasal'];

        $vertical = VectorMath::projectVector(VectorMath::subtract($lensTop, $lensBottom), $verticalAxis);
        $horizontal = VectorMath::projectVector(VectorMath::subtract($lensTemporal, $lensNasal), $horizontalAxis);

        return [
            'b_dimension' => VectorMath::magnitude($vertical),
            'a_dimension' => VectorMath::magnitude($horizontal),
            'lower_rim_drop_left' => VectorMath::magnitude(VectorMath::projectVector(
                VectorMath::subtract($lensBottom, $lowerRim['left']),
                $verticalAxis
            )),
            'lower_rim_drop_right' => VectorMath::magnitude(VectorMath::projectVector(
                VectorMath::subtract($lensBottom, $lowerRim['right']),
                $verticalAxis
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function computePosture(MeasurementCapture $neutral, MeasurementCapture $reading): array
    {
        $neutralPose = $neutral->getHeadPoseVectors();
        $readingPose = $reading->getHeadPoseVectors();

        $forwardAngle = VectorMath::angleBetween($neutralPose['forward'], $readingPose['forward']);
        $tiltAngle = VectorMath::angleBetween($neutralPose['up'], $readingPose['up']);

        return [
            'forward_angle_radians' => $forwardAngle,
            'forward_angle_degrees' => rad2deg($forwardAngle),
            'tilt_angle_radians' => $tiltAngle,
            'tilt_angle_degrees' => rad2deg($tiltAngle),
        ];
    }
}
