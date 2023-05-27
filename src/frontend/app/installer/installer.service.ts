import { Injectable } from '@angular/core';
import { StepAction } from './types';

@Injectable()
export class InstallerService {

    steps: StepAction[] = [];

    constructor() { }

    setStep(step: StepAction) {
        this.steps.push(step);
    }

    isStepAlreadyLaunched(IdsStep: string) {
        return this.steps.filter(step => IdsStep === step.idStep).length > 0;
    }
}
