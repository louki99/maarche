import { Injectable } from '@angular/core';

@Injectable({
    providedIn: 'root'
})

export class SessionStorageService {

    get(key: any) {
        return sessionStorage.getItem(key);
    }

    save(key: any, value: any) {
        sessionStorage.setItem(key, value);
    }

    remove(key: any) {
        sessionStorage.removeItem(key);
    }

    resetSession() {
        for (let i = 0; i < sessionStorage.length; i++) {
            this.remove(sessionStorage.key(i));
        }
    }

    clearAllById(data: any) {
        for ( let i = 0; i < sessionStorage.length; ++i ) {
            const item: string = this.get(sessionStorage.key(i));
            if (item.toString().includes(`basket_${data.basketId}_group_${data.groupId}`)) {
                this.remove(`canGoToNextRes_basket_${data.basketId}_group_${data.groupId}_action_${data.action.id}`);
            }
        }
    }

    checkSessionStorage(inLocalStorage: boolean, canGoToNextRes: boolean, data: any) {
        if (inLocalStorage && !canGoToNextRes) {
            // Delete the object if the value exists in the localstorage and the option is disabled from the administration
            this.remove(`canGoToNextRes_basket_${data.basketId}_group_${data.groupId}_action_${data.action.id}`);
        } else if (canGoToNextRes) {
            this.save(`canGoToNextRes_basket_${data.basketId}_group_${data.groupId}_action_${data.action.id}`, JSON.stringify(`basket_${data.basketId}_group_${data.groupId}`));
        }
    }
}