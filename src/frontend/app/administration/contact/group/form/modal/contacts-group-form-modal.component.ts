import { Component, Inject, OnInit, ViewChild } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { FunctionsService } from '@service/functions.service';
import { ContactsGroupFormComponent } from '../contacts-group-form.component';

@Component({
    templateUrl: 'contacts-group-form-modal.component.html',
    styleUrls: ['contacts-group-form-modal.component.scss'],
})
export class ContactsGroupFormModalComponent implements OnInit{

    @ViewChild('appContactsGroupForm', { static: false }) appContactsGroupForm: ContactsGroupFormComponent;

    loading: boolean = false;

    modalTitle: string = '';
    contactGroupId: number = null;
    canAddCorrespondents: boolean = true;
    canModifyGroupInfo: boolean = true;
    allPerimeters: boolean = true;
    contactIds: number[] = [];

    constructor(
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<ContactsGroupFormModalComponent>,
        private functionsService: FunctionsService) {
    }

    ngOnInit(): void {
        this.modalTitle = !this.functionsService.empty(this.data.modalTitle) ? ' : ' + this.data.modalTitle : '';
        this.contactGroupId = !this.functionsService.empty(this.data.contactGroupId) ? this.data.contactGroupId : null;
        this.canAddCorrespondents = !this.functionsService.empty(this.data.canAddCorrespondents) ? this.data.canAddCorrespondents : true;
        this.canModifyGroupInfo = !this.functionsService.empty(this.data.canModifyGroupInfo) ? this.data.canModifyGroupInfo : true;
        this.allPerimeters = !this.functionsService.empty(this.data.allPerimeters) ? this.data.allPerimeters : true;
        this.contactIds = !this.functionsService.empty(this.data.contactIds) ? this.data.contactIds : [];
    }

    onSubmit() {
        this.appContactsGroupForm.onSubmit();
    }

    isValid() {
        return (this.appContactsGroupForm !== undefined && this.appContactsGroupForm.isValid());
    }

    goTo(id: any) {
        if (this.contactGroupId == null) {
            this.dialogRef.close({
                id: id,
                state: 'create'
            });
        } else {
            this.dialogRef.close({
                id: id,
                state: 'update'
            });
        }
    }
}
