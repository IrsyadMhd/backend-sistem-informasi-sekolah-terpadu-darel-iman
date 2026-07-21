import { StatusBar } from 'expo-status-bar';
import { PaperProvider, MD3LightTheme } from 'react-native-paper';
import BottomTabs from './src/navigation/BottomTabs';

export default function App() {
  return (
    <PaperProvider
      theme={{
        ...MD3LightTheme,
        colors: {
          ...MD3LightTheme.colors,
          primary: '#0f5132',
          secondary: '#10b981',
          tertiary: '#f59e0b',
        },
      }}
    >
      <BottomTabs />
      <StatusBar style="dark" />
    </PaperProvider>
  );
}
