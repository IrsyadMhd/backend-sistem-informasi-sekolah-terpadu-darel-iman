import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import SimpleScreen from '../screens/SimpleScreen';

const Tab = createBottomTabNavigator();

const screenMap = [
  { name: 'Beranda', icon: 'home-variant' },
  { name: 'Absensi', icon: 'clipboard-check' },
  { name: 'Tahfizh', icon: 'book-open-page-variant' },
  { name: 'Tugas', icon: 'notebook' },
  { name: 'Nilai', icon: 'chart-line' },
  { name: 'Jadwal', icon: 'calendar-month' },
  { name: 'Informasi', icon: 'information' },
  { name: 'Profil', icon: 'account-circle' },
];

export default function BottomTabs() {
  return (
    <NavigationContainer>
      <Tab.Navigator
        screenOptions={({ route }) => ({
          headerStyle: { backgroundColor: '#ffffff' },
          headerTitleStyle: { color: '#0f5132' },
          tabBarActiveTintColor: '#0f5132',
          tabBarInactiveTintColor: '#64748b',
          tabBarStyle: { backgroundColor: '#ffffff' },
          tabBarIcon: ({ color, size }) => {
            const current = screenMap.find((s) => s.name === route.name);
            return <MaterialCommunityIcons name={(current?.icon || 'circle') as never} color={color} size={size} />;
          },
        })}
      >
        {screenMap.map((screen) => (
          <Tab.Screen
            key={screen.name}
            name={screen.name}
            children={() => <SimpleScreen title={screen.name} />}
          />
        ))}
      </Tab.Navigator>
    </NavigationContainer>
  );
}
