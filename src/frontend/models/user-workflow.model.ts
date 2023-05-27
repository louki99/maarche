export interface UserWorkflow {
    /**
     * User identifier in the external signatory book
     */
    id?: number;

    /**
     * User identifier in Maarch Courrier
     */
    item_id?: number;

    /**
     * Object identifier
     */
    listinstance_id?: number;

    /**
     * Identifier of the delegating user
     */
    delegatedBy?: number;

    /**
     * Type of item: 'user', 'entity', ...
     */
    item_type: string;


    /**
     * Entity of the item: can be the processing entity or the email address
     */
    item_entity?: string;


    /**
     * Label to display : firstname + last name
     */
    labelToDisplay: string;


    /**
     * Role of item : 'visa', 'stamp', ...
     */
    role?: string;


    /**
     * Date the user made the visa/sign action
     */
    process_date?: string;

    /**
     * User avatar
     */
    picture?: string;

    /**
     * User status
     */
    status?: string;

    /**
     * Diffusion list type: 'VISA_CIRCUIT', 'AVIS_CIRCUIT', ...
     */
    difflist_type?: string;

    /**
     * External identifier
     */
    externalId?: {};

    /**
     * other external information
     */
    externalInformations?: {};

    /**
     * Available roles: 'visa', 'sign', 'inca_card', 'rgs_2stars', .
     */
    availableRoles?: string[];

    /**
     * Indicates whether the user must sign a mail or not
     */
    requested_signature?: boolean;

    /**
     * Indicates whether the user has signed or not
     */
    signatory?: boolean;

    /**
     * Indicates whether the user has the privilege
     */
    hasPrivilege: boolean;

    /**
     * Indicates if the user is valid
     */
    isValid: boolean;

    /**
     * Signature positions
     */
    signaturePositions?: any[];

    /**
     * Date positions
     */
    datePositions?: any[];

    /**
     * Signature modes : 'visa', 'sign'
     */
    signatureModes?: string[];
}

export class UserWorkflow implements UserWorkflow {
    constructor() {
        this.id = null;
        this.item_id = null;
        this.listinstance_id = null;
        this.delegatedBy = null;
        this.item_type = 'user';
        this.item_entity = '';
        this.labelToDisplay = '';
        this.role = '';
        this.process_date = '';
        this.picture = '';
        this.status = '';
        this.difflist_type = 'VISA_CIRCUIT';
        this.signatory = false;
        this.hasPrivilege = false;
        this.isValid = false;
        this.requested_signature = false;
        this.externalId = {};
        this.externalInformations = {};
        this.availableRoles = [];
        this.signaturePositions = [];
        this.datePositions = [];
        this.signatureModes = ['visa', 'sign'];
    }
}
