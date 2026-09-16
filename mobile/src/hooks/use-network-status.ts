import NetInfo from "@react-native-community/netinfo";
import { useEffect, useState } from "react";

/** Reflects live connectivity so the UI can show cached data while offline. */
export function useNetworkStatus() {
  const [isConnected, setIsConnected] = useState(true);

  useEffect(() => {
    const unsubscribe = NetInfo.addEventListener((state) => {
      setIsConnected(
        Boolean(state.isConnected) && state.isInternetReachable !== false,
      );
    });

    return unsubscribe;
  }, []);

  return isConnected;
}
