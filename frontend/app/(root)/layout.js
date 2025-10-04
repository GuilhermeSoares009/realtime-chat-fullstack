import { Inter } from "next/font/google";
import "../globals.css";
import Provider from "@components/Provider";
import TopBar from "@components/TopBar";
import BottomBar from "@components/BottomBar";

const inter = Inter({ subsets: ["latin"] });

export const metadata = {
  title: "Chat App",
  description: "Real Time Chat App ",
};

export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <body className={`${inter.className} bg-blue-2`}>
        <Provider>
          <TopBar />
          {children}
          <BottomBar />
        </Provider>
      </body>
    </html>
  );
}
