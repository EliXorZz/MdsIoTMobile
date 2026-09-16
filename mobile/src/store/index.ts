import AsyncStorage from "@react-native-async-storage/async-storage";
import NetInfo from "@react-native-community/netinfo";
import { combineReducers, configureStore } from "@reduxjs/toolkit";
import { setupListeners } from "@reduxjs/toolkit/query";
import { AppState, type AppStateStatus } from "react-native";
import {
  useDispatch,
  useSelector,
  type TypedUseSelectorHook,
} from "react-redux";
import {
  FLUSH,
  PAUSE,
  PERSIST,
  persistReducer,
  persistStore,
  PURGE,
  REGISTER,
  REHYDRATE,
} from "redux-persist";

import { api } from "./api";

// Seul le cache RTK Query est persisté : les capteurs restent visibles hors-ligne
// avec l'horodatage (fulfilledTimeStamp) de leur dernière récupération réussie.
const persistConfig = {
  key: "root",
  storage: AsyncStorage,
  whitelist: [api.reducerPath],
};

const rootReducer = combineReducers({
  [api.reducerPath]: api.reducer,
});

const persistedReducer = persistReducer(persistConfig, rootReducer);

export const store = configureStore({
  reducer: persistedReducer,
  middleware: (getDefaultMiddleware) =>
    getDefaultMiddleware({
      serializableCheck: {
        ignoredActions: [FLUSH, REHYDRATE, PAUSE, PERSIST, PURGE, REGISTER],
      },
    }).concat(api.middleware),
});

export const persistor = persistStore(store);

// React Native n'émet pas les événements `online`/`visibilitychange` du navigateur :
// on branche NetInfo et AppState pour que `refetchOnReconnect`/`refetchOnFocus` fonctionnent.
setupListeners(
  store.dispatch,
  (dispatch, { onFocus, onFocusLost, onOnline, onOffline }) => {
    const unsubscribeNetInfo = NetInfo.addEventListener((state) => {
      if (state.isConnected && state.isInternetReachable !== false) {
        dispatch(onOnline());
      } else {
        dispatch(onOffline());
      }
    });

    const handleAppStateChange = (status: AppStateStatus) => {
      if (status === "active") {
        dispatch(onFocus());
      } else {
        dispatch(onFocusLost());
      }
    };

    const appStateSubscription = AppState.addEventListener(
      "change",
      handleAppStateChange,
    );

    return () => {
      unsubscribeNetInfo();
      appStateSubscription.remove();
    };
  },
);

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;

export const useAppDispatch: () => AppDispatch = useDispatch;
export const useAppSelector: TypedUseSelectorHook<RootState> = useSelector;
