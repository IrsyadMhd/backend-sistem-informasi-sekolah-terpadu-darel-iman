import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import HomeScreen from '../screens/HomeScreen';
import AbsensiScreen from '../screens/AbsensiScreen';
import TahfizhScreen from '../screens/TahfizhScreen';
import ProfilScreen from '../screens/ProfilScreen';
import SimpleScreen from '../screens/SimpleScreen';

const Tab = createBottomTabNavigator();

export default function BottomTabs() {
  return (
    <NavigationContainer>
      <Tab.Navigator
        screenOptions={({ route }) => ({
          headerStyle: { backgroundColor: '#ffffff' },
          headerTitleStyle: { color: '#0f5132', fontWeight: 'bold' },
          tabBarActiveTintColor: '#0f5132',
          tabBarInactiveTintColor: '#64748b',
          tabBarStyle: { backgroundColor: '#ffffff', paddingBottom: 4, height: 60 },
          tabBarLabelStyle: { fontSize: 11, fontWeight: '500' },
          tabBarIcon: ({ color, size }) => {
            let iconName = 'circle';
            if (route.name === 'Beranda') iconName = 'home-variant';
            else if (route.name === 'Absensi') iconName = 'clipboard-check';
            else if (route.name === 'Tahfizh') iconName = 'book-open-page-variant';
            else if (route.name === 'Tugas') iconName = 'notebook';
            else if (route.name === 'Profil') iconName = 'account-circle';
            return <MaterialCommunityIcons name={iconName as never} color={color} size={size} />;
          },
        })}
      >
        <Tab.Screen
          name="Beranda"
          component={HomeScreen}
          options={{ title: 'SIMS Islam Terpadu' }}
        />
        <Tab.Screen
          name="Absensi"
          component={AbsensiScreen}
          options={{ title: 'Presensi Digital' }}
        />
        <Tab.Screen
          name="Tahfizh"
          component={TahfizhScreen}
          options={{ title: 'Hafalan Al-Qur\'an' }}
        />
        <Tab.Screen
          name="Tugas"
          children={() => <SimpleScreen title="Tugas & Penilaian" />}
          options={{ title: 'Tugas Siswa' }}
        />
        <Tab.Screen
          name="Profil"
          component={ProfilScreen}
          options={{ title: 'Profil Akun' }}
        />
      </Tab.Navigator>
    </NavigationContainer>
  );
}
