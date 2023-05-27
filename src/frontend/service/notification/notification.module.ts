import { NgModule } from '@angular/core';

import { CommonModule } from '@angular/common';

import { AppMaterialModule } from '../../app/app-material.module';
import { CustomSnackbarComponent, NotificationService } from './notification.service';

@NgModule({
    imports: [
        AppMaterialModule,
        CommonModule
    ],
    declarations: [
        CustomSnackbarComponent,
    ],
    exports: [],
    providers: [NotificationService]
})
export class NotificationModule { }
