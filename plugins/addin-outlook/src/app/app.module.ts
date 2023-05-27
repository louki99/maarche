import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { BrowserAnimationsModule } from '@angular/platform-browser/animations';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { AuthInterceptor } from './service/auth-interceptor.service';
import { HttpClientModule, HTTP_INTERCEPTORS } from '@angular/common/http';
import { AppMaterialModule } from './app-material.module';
import { CustomSnackbarComponent } from './service/notification/notification.service';
import AppComponent from './app.component';
import { AppRoutingModule } from './app-routing.module';
import { InternationalizationModule } from './service/translate/internationalization.module';

import { MessageBoxComponent } from './plugins/messageBox/message-box.component';
import { PanelComponent } from './panel/panel.component';
import { LatinisePipe } from 'ngx-pipes';

@NgModule({
    declarations: [
        AppComponent,
        MessageBoxComponent,
        CustomSnackbarComponent,
        PanelComponent,
    ],
    imports: [
        HttpClientModule,
        BrowserModule,
        BrowserAnimationsModule,
        FormsModule,
        AppRoutingModule,
        ReactiveFormsModule,
        AppMaterialModule,
        InternationalizationModule
    ],
    providers: [
        { provide: HTTP_INTERCEPTORS, useClass: AuthInterceptor, multi: true },
        LatinisePipe
    ],
    bootstrap: [AppComponent]
})
export class AppModule {}
