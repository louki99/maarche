import { Component, Inject, OnInit, ViewChild } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { FunctionsService } from '@service/functions.service';
import { ContactsFormComponent } from '../contacts-form.component';

@Component({
    templateUrl: 'contacts-form-modal.component.html',
    styleUrls: ['contacts-form-modal.component.scss'],
})
export class ContactsFormModalComponent implements OnInit {

    @ViewChild('appContactForm', { static: false }) appContactForm: ContactsFormComponent;

    constructor(
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<ContactsFormModalComponent>,
        private functionsService: FunctionsService) {
    }

    ngOnInit(): void { }

    onSubmit() {
        this.appContactForm.onSubmit();
    }

    isValid() {
        return (this.appContactForm !== undefined && this.appContactForm.isValidForm());
    }

    goTo(id: any) {
        this.dialogRef.close({
            id: id,
            state: 'create'
        });
    }
}
