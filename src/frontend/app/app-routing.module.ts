import { NgModule } from '@angular/core';
import { RouterModule, Routes, PreloadAllModules } from '@angular/router';
import { AppGuard, AfterProcessGuard } from '@service/app.guard';

import { ActivateUserComponent } from './activate-user.component';
import { PasswordModificationComponent } from './login/passwordModification/password-modification.component';
import { ProfileComponent } from './profile/profile.component';
import { HomeComponent } from './home/home.component';
import { SaveNumericPackageComponent } from './save-numeric-package.component';
import { PrintSeparatorComponent } from './separator/print-separator/print-separator.component';
import { ForgotPasswordComponent } from './login/forgotPassword/forgotPassword.component';
import { ResetPasswordComponent } from './login/resetPassword/reset-password.component';
import { DocumentViewerPageComponent } from './viewer/page/document-viewer-page.component';
import { LoginComponent } from './login/login.component';
import { SignatureBookComponent } from './signature-book.component';
import { FollowedDocumentListComponent } from './home/followed-list/followed-document-list.component';
import { FolderDocumentListComponent } from './folder/document-list/folder-document-list.component';
import { BasketListComponent } from './list/basket-list.component';
import { AcknowledgementReceptionComponent } from './registeredMail/acknowledgement-reception/acknowledgement-reception.component';
import { SearchComponent } from './search/search.component';
import { ProcessComponent } from './process/process.component';
import { IndexationComponent } from './indexation/indexation.component';
import { AppLightGuard } from '@service/app-light.guard';


const routes: Routes = [
    { path: 'resources/:resId/content', canActivate: [AppGuard], component: DocumentViewerPageComponent },
    {
        path: 'install',
        canActivate: [AppLightGuard],
        loadChildren: () => import('./installer/installer.module').then(m => m.InstallerModule)
    },
    { path: 'signatureBook/users/:userId/groups/:groupId/baskets/:basketId/resources/:resId', canActivate: [AppGuard], component: SignatureBookComponent },
    { path: 'followed', canActivate: [AppGuard], component: FollowedDocumentListComponent },
    { path: 'saveNumericPackage', canActivate: [AppGuard], component: SaveNumericPackageComponent },
    { path: 'separators/print', canActivate: [AppGuard], component: PrintSeparatorComponent },
    { path: 'forgot-password', component: ForgotPasswordComponent },
    { path: 'reset-password', component: ResetPasswordComponent },
    { path: 'activate-user', component: ActivateUserComponent },
    { path: 'password-modification', component: PasswordModificationComponent },
    { path: 'folders/:folderId', canActivate: [AppGuard], component: FolderDocumentListComponent },
    { path: 'profile', canActivate: [AppGuard], component: ProfileComponent },
    { path: 'home', canActivate: [AppGuard], component: HomeComponent },
    { path: 'basketList/users/:userSerialId/groups/:groupSerialId/baskets/:basketId', canActivate: [AppGuard], component: BasketListComponent },
    { path: 'login', canActivate: [AppLightGuard], component: LoginComponent },
    { path: 'registeredMail/acknowledgement', canActivate: [AppGuard], component: AcknowledgementReceptionComponent },
    { path: 'search', canActivate: [AppGuard], component: SearchComponent },
    {
        path: 'process/users/:userSerialId/groups/:groupSerialId/baskets/:basketId/resId/:resId',
        canActivate: [AppGuard],
        canDeactivate: [AfterProcessGuard],
        component: ProcessComponent
    },
    {
        path: 'resources/:detailResId',
        canActivate: [AppGuard],
        canDeactivate: [AfterProcessGuard],
        component: ProcessComponent
    },
    {
        path: 'indexing/:groupId',
        canActivate: [AppGuard],
        component: IndexationComponent
    },
    {
        path: '',
        redirectTo: 'home',
        pathMatch: 'full'
    },
];
@NgModule({
    imports: [
        RouterModule.forRoot(routes, { preloadingStrategy: PreloadAllModules, useHash: true, relativeLinkResolution: 'legacy' })
    ],
    exports: [
        RouterModule
    ]
})
export class AppRoutingModule { }
