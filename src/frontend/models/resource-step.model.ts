export interface ResourceStep {
    /**
     * Resource id
    */
    resId: number;

    /**
     * Indicates whether the the main document
    */
    mainDocument: boolean;

    /**
     * The identifier of the user in the external signatory book
    */
    externalId: string | number;

    /**
     * The order of the user in the workflow
    */
    sequence: number;

    /**
     * User role : 'visa', 'vign'
    */
    action: string;

    /**
     * Signature mode
    */
    signatureMode: string;

    /**
     * Signature positions
    */
    signaturePositions?: any[];

    /**
     * Date positions
    */
    datePositions?: any[];

    /**
     * Information related to OTP users
     */
    externalInformations: Object;
}

export class ResourceStep implements ResourceStep {
    constructor() {
        this.resId = null;
        this.mainDocument = false;
        this.externalId = null;
        this.sequence = null;
        this.action = '';
        this.signatureMode = '';
        this.signaturePositions = [];
        this.datePositions = [];
        this.externalInformations = {};
    }
}
