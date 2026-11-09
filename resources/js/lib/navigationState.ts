import { createContext, useContext } from 'react';
export const NavigationState = createContext({ loading: false });
export function useNavigationState() {
    return useContext(NavigationState);
}
